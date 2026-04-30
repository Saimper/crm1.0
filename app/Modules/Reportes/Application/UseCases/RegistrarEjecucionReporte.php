<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\UseCases;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class RegistrarEjecucionReporte
{
    public function execute(
        int $definicionId,
        int $proyectoId,
        int $usuarioId,
        string $formato,
        int $totalFilas,
        int $duracionMs,
    ): void {
        DB::table('reportes_ejecuciones')->insert([
            'public_id' => (string) Str::ulid(),
            'definicion_id' => $definicionId,
            'proyecto_id' => $proyectoId,
            'usuario_id' => $usuarioId,
            'formato' => $formato,
            'total_filas' => $totalFilas,
            'duracion_ms' => $duracionMs,
            'ejecutado_en' => now(),
        ]);
    }
}
