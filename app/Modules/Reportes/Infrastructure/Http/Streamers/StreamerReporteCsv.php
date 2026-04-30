<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Infrastructure\Http\Streamers;

use App\Modules\Reportes\Application\DTOs\ResultadoEjecucionReporte;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streaming CSV nativo siguiendo precedente F19 (BOM UTF-8 + fputcsv).
 *
 * Sin límite de filas: el generator del resultado se itera fila a fila
 * directamente sobre php://output. Memoria O(1) por fila.
 */
final class StreamerReporteCsv
{
    /**
     * @param  callable(int $totalFilas): void|null  $onComplete  Callback opcional para registrar ejecución.
     */
    public function stream(
        ResultadoEjecucionReporte $resultado,
        string $filename,
        ?callable $onComplete = null,
    ): StreamedResponse {
        return new StreamedResponse(function () use ($resultado, $onComplete): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");

            $cabeceras = array_map(static fn (array $h): string => $h['etiqueta'], $resultado->cabeceras);
            fputcsv($out, $cabeceras);

            $total = 0;
            foreach ($resultado->filas as $fila) {
                $i = 0;
                $valores = [];
                foreach ($resultado->cabeceras as $_) {
                    $valores[] = self::formatearValor($fila['col_'.$i] ?? null);
                    $i++;
                }
                fputcsv($out, $valores);
                $total++;
            }

            fclose($out);

            if ($onComplete !== null) {
                $onComplete($total);
            }
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private static function formatearValor(mixed $v): string
    {
        if ($v === null) {
            return '';
        }
        if (is_bool($v)) {
            return $v ? '1' : '0';
        }
        if ($v instanceof \DateTimeInterface) {
            return $v->format('Y-m-d H:i:s');
        }

        return (string) $v;
    }
}
