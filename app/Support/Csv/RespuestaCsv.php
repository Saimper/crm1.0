<?php

declare(strict_types=1);

namespace App\Support\Csv;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Una descarga CSV que no se trae la tabla entera a memoria.
 *
 * Las cuatro exportaciones antiguas hacían `->get()` y luego envolvían el
 * resultado en un StreamedResponse: el streaming era de mentira, porque el
 * conjunto completo ya estaba en PHP antes de escribir el primer byte. Y
 * `cursor()` no arregla eso en MySQL con PDO en modo buffered —que es el de
 * esta aplicación—: el cliente descarga el resultado entero en `execute()` y
 * sólo difiere la hidratación. Lo que sí acota la memoria es paginar por clave
 * (`chunkById`): cada lote es UNA consulta con `id > último` y `LIMIT`, sin
 * OFFSET que recorra lo ya servido, y la memoria es la del lote.
 *
 * De paso resuelve tres cosas que ninguna exportación hacía y que un helper
 * único puede hacer por todas:
 *
 *  - `flush()` tras cada lote, para que nginx reciba bytes y no corte la
 *    descarga por inactividad. Con el buffer de salida de PHP-FPM activo, sin
 *    esto el fichero sale de golpe al final o no sale.
 *  - `set_time_limit(0)`: el corte de `max_execution_time` dejaba CSVs
 *    truncados que parecían completos. (No cubre los timeouts de FPM ni de
 *    nginx, que gobiernan igual y hay que mirar en el servidor.)
 *  - Neutralizar celdas Y cabeceras que Excel evaluaría como fórmula (`=`,
 *    `+`, `-`, `@`): las notas de gestión, las filas rechazadas de una
 *    importación y los nombres de columna de un archivo subido son texto que
 *    escribió otra persona, y abrirlos en una hoja de cálculo no debería
 *    ejecutar nada.
 *  - Dejar la huella de la descarga AUNQUE se corte: `ignore_user_abort` y un
 *    `finally` que llama a `$alTerminar` con lo que se alcanzó a emitir y si
 *    la descarga terminó entera. Un supervisor que cancela al 90 % ya se llevó
 *    el 90 % de las filas, y eso tiene que constar igual.
 *
 * El fichero sale con BOM UTF-8 para que Excel lo abra con acentos, igual que
 * las exportaciones que sustituye.
 */
final class RespuestaCsv
{
    /**
     * @param  list<string>  $cabeceras
     * @param  Builder  $consulta  Consulta YA recortada por proyecto. Se le quita cualquier
     *                             orden y se pagina por `$columnaId`, así que las filas salen
     *                             por id ascendente.
     * @param  string  $columnaId  Columna de paginación, calificada (`p.id`).
     * @param  string  $aliasId  Cómo se llama esa columna en la fila devuelta (`id`).
     * @param  callable(\stdClass, mixed): list<mixed>  $fila  Convierte un registro en celdas. El
     *                                                         segundo argumento es lo que devolvió `$prepararLote`.
     * @param  (callable(Collection<int, \stdClass>): mixed)|null  $prepararLote  Se llama una vez por lote
     *                                                                            con los registros, para cargar de una vez lo que las filas
     *                                                                            necesiten (valores de campos personalizados, catálogos).
     * @param  (callable(int, bool): void)|null  $alTerminar  Recibe las filas escritas y si la
     *                                                        descarga terminó entera (false si el cliente cortó o
     *                                                        una fila reventó a mitad). Se llama siempre.
     */
    public static function desdeConsulta(
        string $nombreFichero,
        array $cabeceras,
        Builder $consulta,
        string $columnaId,
        string $aliasId,
        callable $fila,
        ?callable $prepararLote = null,
        ?callable $alTerminar = null,
        int $tamanoLote = 500,
    ): StreamedResponse {
        $nombreFichero = self::nombreSeguro($nombreFichero);

        return new StreamedResponse(function () use ($cabeceras, $consulta, $columnaId, $aliasId, $fila, $prepararLote, $alTerminar, $tamanoLote): void {
            set_time_limit(0);
            // Sin esto PHP mata el script en el siguiente flush() cuando el
            // navegador cancela, y la huella de abajo no llegaría a escribirse.
            ignore_user_abort(true);
            self::abrirLaSalida();

            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_map(self::celda(...), $cabeceras));

            $total = 0;
            $completa = false;

            try {
                $consulta->reorder()->chunkById($tamanoLote, function (Collection $lote) use ($out, $fila, $prepararLote, &$total): bool {
                    $contexto = $prepararLote !== null ? $prepararLote($lote) : null;

                    foreach ($lote as $registro) {
                        fputcsv($out, array_map(self::celda(...), $fila($registro, $contexto)));
                        $total++;
                    }

                    self::empujar();

                    // Si el cliente se fue, no hay a quién seguir escribiendo:
                    // se para y la huella dice «incompleta».
                    return ! connection_aborted();
                }, $columnaId, $aliasId);

                $completa = ! connection_aborted();
            } finally {
                fclose($out);

                if ($alTerminar !== null) {
                    $alTerminar($total, $completa);
                }
            }
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$nombreFichero.'"',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Un valor cualquiera, como texto de celda.
     *
     * Los booleanos salen como «sí»/«no» porque el CSV lo lee una persona en
     * Excel, no otro programa. Las fechas con hora se dan por formateadas por
     * quien llama (es quien sabe la zona del cliente); aquí sólo se cubre el
     * caso de que llegue un objeto sin formatear.
     */
    public static function celda(mixed $valor): string
    {
        if ($valor === null) {
            return '';
        }
        if (is_bool($valor)) {
            return $valor ? 'sí' : 'no';
        }
        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('Y-m-d H:i:s');
        }

        $texto = (string) $valor;

        // Una celda que empieza por `=`, `+`, `-`, `@` (o un tabulador) la
        // evalúan Excel y LibreOffice como fórmula al abrir el fichero. Un
        // apóstrofo delante la deja como texto literal. Los números negativos
        // se respetan: «-120.50» es un importe, no una fórmula.
        if ($texto !== '' && ! is_numeric($texto) && str_contains("=+-@\t\r", $texto[0])) {
            return "'".$texto;
        }

        return $texto;
    }

    /**
     * El nombre lleva dentro códigos de proyecto o de mandante, que son datos
     * de la base: no pueden acabar en una cabecera HTTP sin limpiar.
     */
    public static function nombreSeguro(string $nombre): string
    {
        $limpio = preg_replace('/[^A-Za-z0-9._-]/', '_', $nombre) ?? 'descarga.csv';

        return $limpio !== '' ? $limpio : 'descarga.csv';
    }

    /**
     * Cierra los buffers de salida de PHP para que lo que se escriba vaya al
     * cliente según se produce. En la suite no: `streamedContent()` captura la
     * respuesta justamente con un buffer, y cerrarlo la haría desaparecer.
     */
    private static function abrirLaSalida(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        while (ob_get_level() > 0) {
            ob_end_flush();
        }
    }

    private static function empujar(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        flush();
    }
}
