<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Infrastructure\Http\Streamers;

use App\Modules\Reportes\Application\DTOs\ResultadoEjecucionReporte;
use App\Support\Csv\RespuestaCsv;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streaming CSV nativo siguiendo precedente F19 (BOM UTF-8 + fputcsv).
 *
 * Sin límite de filas: el generator del resultado se itera fila a fila
 * directamente sobre php://output. Memoria O(1) por fila.
 *
 * `$onComplete` se llama SIEMPRE, también si la descarga se corta a la mitad,
 * y recibe si llegó entera: quien canceló al 90 % se llevó el 90 % de las
 * filas, y eso tiene que constar igual en la auditoría.
 */
final class StreamerReporteCsv
{
    /** Cada cuántas filas se empuja lo escrito hacia el cliente. */
    private const FILAS_POR_EMPUJE = 500;

    /**
     * @param  (callable(int $totalFilas, bool $completa): void)|null  $onComplete  Registro de la ejecución.
     */
    public function stream(
        ResultadoEjecucionReporte $resultado,
        string $filename,
        ?callable $onComplete = null,
    ): StreamedResponse {
        return new StreamedResponse(function () use ($resultado, $onComplete): void {
            // Lo mismo que hace `RespuestaCsv`, y por lo mismo: sin quitar el
            // límite de ejecución sale un CSV truncado con pinta de completo, y
            // sin `ignore_user_abort` PHP muere al cancelar el cliente y la
            // huella de abajo no llega a escribirse.
            set_time_limit(0);
            ignore_user_abort(true);

            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            $total = 0;
            $completa = false;

            try {
                fwrite($out, "\xEF\xBB\xBF");

                $cabeceras = array_map(static fn (array $h): string => $h['etiqueta'], $resultado->cabeceras);
                fputcsv($out, $cabeceras);

                foreach ($resultado->filas as $fila) {
                    $i = 0;
                    $valores = [];
                    foreach ($resultado->cabeceras as $_) {
                        $valores[] = self::formatearValor($fila['col_'.$i] ?? null);
                        $i++;
                    }
                    fputcsv($out, $valores);
                    $total++;

                    // Empujar cada tanto: nginx da por muerto al upstream que
                    // no escribe, y aquí una fila puede tardar lo que tarde el
                    // JOIN contra los valores de campos personalizados.
                    if ($total % self::FILAS_POR_EMPUJE === 0) {
                        flush();

                        // Y si el cliente se fue, se para: seguir leyendo el
                        // resultado entero para no escribirlo a nadie es
                        // gastar la base de datos por nada.
                        if (connection_aborted()) {
                            break;
                        }
                    }
                }

                $completa = ! connection_aborted();
            } finally {
                fclose($out);

                if ($onComplete !== null) {
                    $onComplete($total, $completa);
                }
            }
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.RespuestaCsv::nombreSeguro($filename).'"',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * La misma celda que el resto de descargas.
     *
     * Delega en `RespuestaCsv::celda()` para no tener dos criterios: de ahí
     * salen los booleanos como «sí»/«no» y, sobre todo, la comilla delante de
     * lo que Excel evaluaría como fórmula. El DSL de F32 expone las notas de
     * gestión, que las escribe otra persona, así que este CSV era el único que
     * podía abrir la calculadora al abrirse.
     */
    private static function formatearValor(mixed $v): string
    {
        return RespuestaCsv::celda($v);
    }
}
