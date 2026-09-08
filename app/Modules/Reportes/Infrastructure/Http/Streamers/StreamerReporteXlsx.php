<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Infrastructure\Http\Streamers;

use App\Modules\Reportes\Application\DTOs\ResultadoEjecucionReporte;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streaming XLSX vía OpenSpout. Sin límite 64K (vs XLS legado).
 *
 * Escribe directo a php://output desde un closure de StreamedResponse.
 * Memoria O(1) por fila.
 */
final class StreamerReporteXlsx
{
    /**
     * @param  (callable(int $totalFilas, bool $completa): void)|null  $onComplete
     */
    public function stream(
        ResultadoEjecucionReporte $resultado,
        string $filename,
        ?callable $onComplete = null,
    ): StreamedResponse {
        return new StreamedResponse(function () use ($resultado, $onComplete): void {
            set_time_limit(0);
            ignore_user_abort(true);

            $writer = new Writer;
            $writer->openToFile('php://output');

            $total = 0;
            $completa = false;

            try {
                $cabeceras = array_map(static fn (array $h): string => $h['etiqueta'], $resultado->cabeceras);
                $writer->addRow(Row::fromValues($cabeceras));

                foreach ($resultado->filas as $fila) {
                    $i = 0;
                    $valores = [];
                    foreach ($resultado->cabeceras as $_) {
                        $valores[] = self::formatearValor($fila['col_'.$i] ?? null);
                        $i++;
                    }
                    $writer->addRow(Row::fromValues($valores));
                    $total++;
                }

                $completa = ! connection_aborted();
            } finally {
                $writer->close();

                if ($onComplete !== null) {
                    $onComplete($total, $completa);
                }
            }
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private static function formatearValor(mixed $v): mixed
    {
        if ($v === null) {
            return '';
        }
        if (is_bool($v)) {
            return $v ? 1 : 0;
        }
        if ($v instanceof \DateTimeInterface) {
            return $v->format('Y-m-d H:i:s');
        }

        return $v;
    }
}
