<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Application\UseCases;

use App\Modules\Importaciones\Domain\Enums\EstadoImportacion;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;

/**
 * Borra el contenido del archivo del cliente de las importaciones terminadas.
 *
 * `importacion_filas.payload` es la fila tal como venía en el CSV: cédula,
 * nombre, teléfonos, saldos. Se guarda porque el motor la lee mientras procesa
 * y porque el supervisor descarga las filas rechazadas para corregirlas y
 * volver a subirlas. Pasado ese uso no le sirve a nadie, y hasta ahora no
 * caducaba: en la base de desarrollo eran 84 MB, el 79% de la tabla, con
 * archivos de hace meses enteros dentro.
 *
 * Se vacía a `{}` y no a NULL porque la columna es `json NOT NULL`, y cambiar
 * eso obligaría a un ALTER sobre la tabla más grande del esquema para no ganar
 * nada: quien pregunta si una importación está depurada mira
 * `importaciones.payload_purgado_en`, no la fila.
 *
 * También se vacía `mensaje_error`: por diseño puede llevar el valor de una
 * celda del cliente, y una vez que el payload no está, el motivo suelto ya no
 * explica nada que se pueda mirar.
 *
 * Entran las TERMINADAS, y también las que se quedaron colgadas en
 * `procesando`. Una terminada no vuelve a ejecutarse —`puedeEncolarse()` sólo
 * admite PREPARADA— así que vaciar sus filas no le quita el trabajo a nadie. Y
 * una colgada tampoco: nada en la aplicación devuelve a terminal una
 * importación cuyo worker murió, así que si no entrara aquí, su archivo se
 * quedaría en la base para siempre, que es justo lo que esto existe para
 * impedir. El plazo las protege de sobra: un mes después, ningún worker va a
 * volver a por ella.
 *
 * Si algún día se permite reintentar una importación fallida o reanudar una
 * colgada, esta purga tiene que cambiar con ella.
 */
final readonly class PurgarPayloadsDeImportaciones
{
    /**
     * Filas por sentencia. Lotes cortos para no tener bloqueada la tabla de
     * filas mientras otro import escribe en ella.
     */
    private const FILAS_POR_LOTE = 1000;

    /** El objeto vacío que sustituye al contenido. */
    private const VACIO = '{}';

    public function __construct(private ConnectionInterface $db) {}

    /**
     * @return array<int, int> importacion_id => filas depuradas
     */
    public function execute(int $diasRetencion, bool $simulacro = false): array
    {
        $limite = CarbonImmutable::now()->subDays(max(1, $diasRetencion));

        $estados = array_map(
            static fn (EstadoImportacion $estado): string => $estado->value,
            array_filter(
                EstadoImportacion::cases(),
                static fn (EstadoImportacion $e): bool => $e->esTerminal() || $e === EstadoImportacion::PROCESANDO,
            ),
        );

        $importaciones = $this->db->table('importaciones')
            ->whereIn('estado', $estados)
            ->whereNull('payload_purgado_en')
            // `terminado_en` es nulo en las colgadas y en las anteriores al
            // modo asíncrono: para ésas manda cuándo empezó, y si tampoco eso,
            // cuándo se creó.
            ->whereRaw('COALESCE(terminado_en, iniciado_en, creada_en) < ?', [$limite])
            ->orderBy('id')
            ->pluck('id');

        $depuradas = [];

        foreach ($importaciones as $importacionId) {
            $importacionId = (int) $importacionId;
            $depuradas[$importacionId] = $simulacro
                ? $this->contarPendientes($importacionId)
                : $this->depurar($importacionId);
        }

        return $depuradas;
    }

    private function contarPendientes(int $importacionId): int
    {
        return $this->db->table('importacion_filas')
            ->where('importacion_id', $importacionId)
            ->whereRaw('payload <> CAST(? AS JSON)', [self::VACIO])
            ->count();
    }

    /**
     * Cursor sobre la PK, como el motor: cada lote es una sentencia acotada y
     * la siguiente arranca donde acabó la anterior, sin OFFSET que recorra lo
     * ya depurado.
     */
    private function depurar(int $importacionId): int
    {
        $total = 0;

        // Arranca en la primera fila de ESTA importación y no en 0: con
        // `id > 0` la primera vuelta es un rango del índice agrupado desde el
        // principio de la tabla, y en una tabla de 50.000 filas eso es recorrer
        // todo lo que hay por delante para juntar el primer lote.
        $ultimoId = (int) $this->db->table('importacion_filas')
            ->where('importacion_id', $importacionId)
            ->min('id') - 1;

        while (true) {
            $ids = $this->db->table('importacion_filas')
                ->where('importacion_id', $importacionId)
                ->where('id', '>', $ultimoId)
                ->whereRaw('payload <> CAST(? AS JSON)', [self::VACIO])
                ->orderBy('id')
                ->limit(self::FILAS_POR_LOTE)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $ultimoId = (int) $ids->last();

            $total += $this->db->table('importacion_filas')
                ->whereIn('id', $ids)
                ->update([
                    'payload' => $this->db->raw("CAST('".self::VACIO."' AS JSON)"),
                    'mensaje_error' => null,
                    // La columna se pisa con ON UPDATE CURRENT_TIMESTAMP, y la
                    // fecha en que se depuró no es la fecha de la fila.
                    'actualizada_en' => $this->db->raw('actualizada_en'),
                ]);
        }

        $this->db->table('importaciones')
            ->where('id', $importacionId)
            ->update(['payload_purgado_en' => CarbonImmutable::now()]);

        return $total;
    }
}
