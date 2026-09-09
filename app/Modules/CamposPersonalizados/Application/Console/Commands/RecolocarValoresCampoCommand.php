<?php

declare(strict_types=1);

namespace App\Modules\CamposPersonalizados\Application\Console\Commands;

use App\Modules\CamposPersonalizados\Domain\ValueObjects\TipoCampo;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Mueve los valores de un campo a la columna que le corresponde por su tipo.
 *
 * No es lo mismo que `campos:convertir-tipo`. Aquel cambia el tipo de un campo
 * de texto a su tipo real. Este arregla el caso contrario: el tipo YA es el
 * bueno y los valores se quedaron atrás.
 *
 * Pasa cuando alguien cambia el tipo desde la UI. La pantalla actualiza
 * `campos_personalizados.tipo` y no toca `valores_campo_personalizado`, así que
 * el lector —que mira la columna del tipo nuevo— ve `null` y el campo aparece
 * vacío aunque el dato siga ahí. En la réplica de producción son 22.623 valores
 * en tres campos de tipo `moneda` cuyo contenido vive en `valor_texto_corto`.
 *
 * Las dos defensas de `campos:convertir-tipo` valen igual aquí:
 *
 *  1. Ceros a la izquierda: un valor que empieza por cero es un identificador,
 *     no una cantidad. Se aborta salvo `--forzar`.
 *  2. Valores que no parsean: se aborta y se listan, en vez de perderlos.
 */
final class RecolocarValoresCampoCommand extends Command
{
    protected $signature = 'campos:recolocar-valores
                            {campo? : ID del campo personalizado; omítelo con --proyecto para recorrerlos todos}
                            {--proyecto= : Recorre todos los campos del proyecto y recoloca los que lo necesiten}
                            {--moneda=USD : Código de divisa a escribir cuando el destino es moneda}
                            {--dry-run : Solo informa qué pasaría}
                            {--forzar : Recoloca aunque haya ceros a la izquierda}';

    protected $description = 'Mueve los valores de un campo a la columna que le corresponde por su tipo';

    /** Tipo => columna donde el lector espera el valor. */
    private const DESTINO = [
        'texto_corto' => 'valor_texto_corto',
        'texto_largo' => 'valor_texto_largo',
        'numero_entero' => 'valor_numero_entero',
        'numero_decimal' => 'valor_numero_decimal',
        'fecha' => 'valor_fecha',
        'fecha_hora' => 'valor_fecha_hora',
        'booleano' => 'valor_booleano',
        'seleccion_unica' => 'valor_opcion_id',
        'seleccion_multiple' => 'valor_opciones_ids',
        'moneda' => 'valor_moneda_monto',
    ];

    /** Columnas donde puede haberse quedado un valor huérfano. */
    private const ORIGENES = ['valor_texto_corto', 'valor_texto_largo', 'valor_numero_decimal', 'valor_numero_entero'];

    public function handle(): int
    {
        $campos = $this->camposObjetivo();

        if ($campos === null) {
            return self::FAILURE;
        }

        if ($campos === []) {
            $this->info('No hay ningún campo con valores fuera de su sitio.');

            return self::SUCCESS;
        }

        $fallo = false;
        foreach ($campos as $campo) {
            if ($this->recolocarCampo($campo) === self::FAILURE) {
                $fallo = true;
            }
        }

        return $fallo ? self::FAILURE : self::SUCCESS;
    }

    /** @return list<stdClass>|null null si los argumentos no son válidos */
    private function camposObjetivo(): ?array
    {
        $campoId = $this->argument('campo');
        $proyectoId = $this->option('proyecto');

        if ($campoId === null && $proyectoId === null) {
            $this->error('Indica un id de campo o --proyecto=N.');

            return null;
        }

        $query = DB::table('campos_personalizados')->select(['id', 'codigo', 'etiqueta', 'tipo', 'proyecto_id']);

        if ($campoId !== null) {
            $campo = $query->where('id', (int) $campoId)->first();

            if ($campo === null) {
                $this->error("No existe el campo {$campoId}.");

                return null;
            }

            return [$campo];
        }

        $todos = $query->where('proyecto_id', (int) $proyectoId)->orderBy('id')->get()->all();

        return array_values(array_filter($todos, fn (stdClass $c): bool => $this->descolocados($c)->isNotEmpty()));
    }

    private function recolocarCampo(stdClass $campo): int
    {
        $destino = self::DESTINO[$campo->tipo] ?? null;

        if ($destino === null) {
            $this->error("Tipo desconocido en el campo {$campo->codigo}: {$campo->tipo}.");

            return self::FAILURE;
        }

        $filas = $this->descolocados($campo);

        if ($filas->isEmpty()) {
            $this->line("· {$campo->codigo}: nada que recolocar.");

            return self::SUCCESS;
        }

        $this->line("· {$campo->codigo} ({$campo->tipo}, proyecto {$campo->proyecto_id}): {$filas->count()} valores fuera de {$destino}.");

        $brutos = $filas->map(fn (array $f): string => $f['bruto']);

        if (! $this->option('forzar')
            && in_array($campo->tipo, ['numero_entero', 'numero_decimal', 'moneda'], true)
            && $brutos->contains(fn (string $v): bool => $this->empiezaPorCero($v))) {
            $this->error('  Hay valores que empiezan por cero: parece un identificador, no una cantidad. Usa --forzar si estás seguro.');

            return self::FAILURE;
        }

        [$traducidos, $fallidos] = $this->traducir($filas, TipoCampo::from((string) $campo->tipo));

        if ($fallidos !== []) {
            $this->error('  '.count($fallidos)." valores no encajan en {$campo->tipo}. No se recoloca nada de este campo.");
            foreach (array_slice($fallidos, 0, 10) as $v) {
                $this->line('    · '.var_export($v, true));
            }

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->info("  Simulación: {$filas->count()} valores pasarían a {$destino}.");

            return self::SUCCESS;
        }

        $moneda = (string) $this->option('moneda');

        DB::transaction(function () use ($traducidos, $filas, $destino, $campo, $moneda): void {
            $origenPorFila = $filas->keyBy('id');

            foreach (array_chunk($traducidos, 500, true) as $lote) {
                foreach ($lote as $id => $valor) {
                    $update = [
                        $destino => $valor,
                        (string) $origenPorFila[$id]['columna'] => null,
                    ];

                    if ($campo->tipo === TipoCampo::MONEDA->value) {
                        $update['valor_moneda_codigo'] = $moneda;
                    }

                    DB::table('valores_campo_personalizado')->where('id', $id)->update($update);
                }
            }
        });

        $this->info('  Listo: '.count($traducidos)." valores movidos a {$destino}.");

        return self::SUCCESS;
    }

    /**
     * Filas del campo cuyo valor está en una columna que no es la de su tipo.
     *
     * @return Collection<int, array{id: int, columna: string, bruto: string}>
     */
    private function descolocados(stdClass $campo): Collection
    {
        $destino = self::DESTINO[$campo->tipo] ?? null;

        if ($destino === null) {
            return collect();
        }

        $origenes = array_values(array_diff(self::ORIGENES, [$destino]));

        $filas = DB::table('valores_campo_personalizado')
            ->where('campo_personalizado_id', $campo->id)
            ->whereNull($destino)
            ->where(function ($q) use ($origenes): void {
                foreach ($origenes as $col) {
                    $q->orWhere(function ($w) use ($col): void {
                        $w->whereNotNull($col)->where($col, '!=', '');
                    });
                }
            })
            ->get(array_merge(['id'], $origenes));

        return $filas->map(function (stdClass $fila) use ($origenes): array {
            foreach ($origenes as $col) {
                $v = $fila->{$col};
                if ($v !== null && (string) $v !== '') {
                    return ['id' => (int) $fila->id, 'columna' => (string) $col, 'bruto' => (string) $v];
                }
            }

            return ['id' => (int) $fila->id, 'columna' => (string) $origenes[0], 'bruto' => ''];
        });
    }

    private function empiezaPorCero(string $bruto): bool
    {
        $v = trim($bruto);

        return strlen($v) > 1 && $v[0] === '0' && $v[1] !== '.';
    }

    /**
     * @param  Collection<int, array{id: int, columna: string, bruto: string}>  $filas
     * @return array{0: array<int, mixed>, 1: list<string>}
     */
    private function traducir($filas, TipoCampo $tipo): array
    {
        $ok = [];
        $fallidos = [];

        foreach ($filas as $fila) {
            $bruto = trim($fila['bruto']);
            $valor = $this->parsear($bruto, $tipo);

            if ($valor === null) {
                $fallidos[] = $bruto;

                continue;
            }

            $ok[$fila['id']] = $valor;
        }

        return [$ok, $fallidos];
    }

    private function parsear(string $bruto, TipoCampo $tipo): int|string|null
    {
        return match ($tipo) {
            TipoCampo::NUMERO_ENTERO => preg_match('/^-?\d+$/', $bruto) === 1 ? (int) $bruto : null,
            TipoCampo::NUMERO_DECIMAL, TipoCampo::MONEDA => $this->parsearImporte($bruto),
            TipoCampo::FECHA => $this->parsearFecha($bruto, 'Y-m-d'),
            TipoCampo::FECHA_HORA => $this->parsearFecha($bruto, 'Y-m-d H:i:s'),
            TipoCampo::TEXTO_CORTO, TipoCampo::TEXTO_LARGO => $bruto,
            default => null,
        };
    }

    /** Acepta `1234.56`, `1,234.56` y `$1,234.56`; rechaza cualquier otra cosa. */
    private function parsearImporte(string $bruto): ?string
    {
        $limpio = str_replace([',', '$', ' '], '', $bruto);

        return preg_match('/^-?\d+(\.\d+)?$/', $limpio) === 1 ? $limpio : null;
    }

    private function parsearFecha(string $bruto, string $formato): ?string
    {
        $ts = strtotime($bruto);

        return $ts === false ? null : date($formato, $ts);
    }
}
