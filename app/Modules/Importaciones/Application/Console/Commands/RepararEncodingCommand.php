<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Application\Console\Commands;

use App\Modules\Importaciones\Application\Services\NormalizadorEncoding;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use stdClass;

/**
 * Repara en la base lo que entró con doble codificación antes de que el
 * lector la corrigiera al leer.
 *
 * Aplica `NormalizadorEncoding::repararDobleCodificacion` sobre las columnas
 * de texto que se llenan desde un archivo: nombres y razón social de la
 * persona, la etiqueta del contacto, los valores de texto de los campos
 * personalizados y los textos libres de los CTI. Es idempotente: una celda
 * ya limpia no contiene «Ã», «Â» ni «â» y no entra en la selección.
 *
 * Lo que no se puede reparar —el patrón «ÃÑ» del proyecto 8, donde É, Ñ y Ú
 * colapsaron en la misma secuencia— se cuenta y se lista, pero NO se toca:
 * adivinar corrompería en silencio los apellidos que acertara a medias. Un
 * «JOÃO» legítimo cae en el mismo recuento por contener la marca; es un falso
 * positivo inofensivo, porque tampoco se modifica.
 *
 * Escribe con DB::table en transacciones de 500 filas (precedente:
 * RescatarCamposNativosCommand) y deja `actualizada_en` como estaba: arreglar
 * la codificación no es un cambio de negocio, y mover esa fecha haría que la
 * pantalla dijera que la persona se editó hoy.
 */
final class RepararEncodingCommand extends Command
{
    protected $signature = 'importaciones:reparar-encoding
                            {--proyecto= : ID del proyecto a reparar}
                            {--todos : Reparar todos los proyectos}
                            {--dry-run : Solo informa cuántas filas se repararían}';

    protected $description = 'Deshace la doble codificación (GONZÃ\x81LEZ → GONZÁLEZ) en los textos que entraron por importación';

    private const TAMANO_LOTE = 500;

    private const MAX_IDS_LISTADOS = 20;

    /** @var list<array{tabla: string, pk: string, columnas: list<string>}> */
    private const OBJETIVOS = [
        ['tabla' => 'personas', 'pk' => 'id', 'columnas' => ['nombres', 'apellidos', 'razon_social']],
        ['tabla' => 'contactos', 'pk' => 'id', 'columnas' => ['etiqueta']],
        ['tabla' => 'casos_ticket_cx', 'pk' => 'caso_id', 'columnas' => ['asunto', 'descripcion']],
        ['tabla' => 'casos_servicio', 'pk' => 'caso_id', 'columnas' => ['direccion_servicio', 'tecnico_asignado']],
        ['tabla' => 'casos_lead_venta', 'pk' => 'caso_id', 'columnas' => ['origen_lead']],
    ];

    public function handle(NormalizadorEncoding $normalizador): int
    {
        $todos = (bool) $this->option('todos');
        $seco = (bool) $this->option('dry-run');

        // Desde la consola la opción llega como texto; desde `artisan()` en los
        // tests, como entero. Las dos formas son un id.
        $proyecto = trim((string) ($this->option('proyecto') ?? ''));
        $proyectoId = ctype_digit($proyecto) ? (int) $proyecto : null;

        if (! $todos && $proyectoId === null) {
            $this->error('Indica --proyecto=<id> o --todos.');

            return self::INVALID;
        }

        $proyectos = $todos
            ? DB::table('proyectos')->orderBy('id')->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all()
            : [$proyectoId];

        foreach ($proyectos as $proyectoId) {
            $this->repararProyecto($proyectoId, $normalizador, $seco);
        }

        $this->info($seco ? 'Simulación terminada: no se escribió nada.' : 'Listo.');

        return self::SUCCESS;
    }

    private function repararProyecto(int $proyectoId, NormalizadorEncoding $normalizador, bool $seco): void
    {
        $this->line(sprintf('Proyecto %d%s', $proyectoId, $seco ? ' (simulación)' : ''));

        $resumen = [];

        foreach (self::OBJETIVOS as $objetivo) {
            $consulta = DB::table($objetivo['tabla'])
                ->where('proyecto_id', $proyectoId)
                ->select(array_merge([$objetivo['pk']], $objetivo['columnas']));

            $resumen[$objetivo['tabla']] = $this->repararTabla(
                $consulta,
                $objetivo['tabla'],
                $objetivo['pk'],
                $objetivo['pk'],
                $objetivo['columnas'],
                $normalizador,
                $seco,
            );
        }

        // Los valores de campos personalizados no llevan proyecto: se recorta
        // por la definición del campo, que sí lo lleva.
        $valores = DB::table('valores_campo_personalizado as v')
            ->join('campos_personalizados as c', 'c.id', '=', 'v.campo_personalizado_id')
            ->where('c.proyecto_id', $proyectoId)
            ->select(['v.id', 'v.valor_texto_corto', 'v.valor_texto_largo']);

        $resumen['valores_campo_personalizado'] = $this->repararTabla(
            $valores,
            'valores_campo_personalizado',
            'v.id',
            'id',
            ['valor_texto_corto', 'valor_texto_largo'],
            $normalizador,
            $seco,
        );

        $this->table(
            ['Tabla', $seco ? 'Reparables' : 'Reparadas', 'Irreparables', 'Ids irreparables'],
            array_map(static fn (string $tabla, array $r): array => [
                $tabla,
                $r['reparadas'],
                $r['irreparables'],
                implode(', ', $r['ids_irreparables']).($r['irreparables'] > self::MAX_IDS_LISTADOS ? ', …' : ''),
            ], array_keys($resumen), $resumen),
        );

        Log::info('importaciones:reparar-encoding', [
            'proyecto_id' => $proyectoId,
            'dry_run' => $seco,
            'tablas' => array_map(static fn (array $r): array => [
                'reparadas' => $r['reparadas'],
                'irreparables' => $r['irreparables'],
            ], $resumen),
        ]);
    }

    /**
     * @param  Builder  $consulta  Ya recortada por proyecto y con la PK y las columnas seleccionadas.
     * @param  string  $columnaId  La PK calificada para paginar (`v.id`).
     * @param  string  $aliasId  Cómo se llama esa PK en la fila devuelta (`id`).
     * @param  list<string>  $columnas
     * @return array{reparadas: int, irreparables: int, ids_irreparables: list<int|string>}
     */
    private function repararTabla(
        Builder $consulta,
        string $tabla,
        string $columnaId,
        string $aliasId,
        array $columnas,
        NormalizadorEncoding $normalizador,
        bool $seco,
    ): array {
        // LIKE BINARY: con la collation unicode_ci un «%Ã%» casaría también con
        // «a», «á» y «A», es decir, con casi todas las filas.
        $consulta->where(function (Builder $q) use ($columnas): void {
            foreach ($columnas as $columna) {
                foreach (['%Ã%', '%Â%', '%â%'] as $marca) {
                    $q->orWhereRaw("`{$columna}` LIKE BINARY ?", [$marca]);
                }
            }
        });

        $reparadas = 0;
        $irreparables = 0;
        $idsIrreparables = [];

        $consulta->chunkById(self::TAMANO_LOTE, function (Collection $lote) use (
            $tabla,
            $aliasId,
            $columnas,
            $normalizador,
            $seco,
            &$reparadas,
            &$irreparables,
            &$idsIrreparables,
        ): void {
            DB::transaction(function () use ($lote, $tabla, $aliasId, $columnas, $normalizador, $seco, &$reparadas, &$irreparables, &$idsIrreparables): void {
                /** @var stdClass $fila */
                foreach ($lote as $fila) {
                    $cambios = [];
                    $sigueRota = false;

                    foreach ($columnas as $columna) {
                        $original = $fila->{$columna};
                        if (! is_string($original) || $original === '') {
                            continue;
                        }

                        $reparada = $normalizador->repararDobleCodificacion($original);
                        if ($reparada !== $original) {
                            $cambios[$columna] = $reparada;
                        }
                        if ($normalizador->pareceDobleCodificada($reparada)) {
                            $sigueRota = true;
                        }
                    }

                    if ($cambios !== []) {
                        $reparadas++;
                        if (! $seco) {
                            $cambios['actualizada_en'] = DB::raw('actualizada_en');
                            DB::table($tabla)->where($aliasId, $fila->{$aliasId})->update($cambios);
                        }
                    }

                    if ($sigueRota) {
                        $irreparables++;
                        if (count($idsIrreparables) < self::MAX_IDS_LISTADOS) {
                            $idsIrreparables[] = $fila->{$aliasId};
                        }
                    }
                }
            });
        }, $columnaId, $aliasId);

        return [
            'reparadas' => $reparadas,
            'irreparables' => $irreparables,
            'ids_irreparables' => $idsIrreparables,
        ];
    }
}
