<?php

declare(strict_types=1);

namespace App\Modules\Auditoria\Infrastructure\Persistence\Repositories;

use App\Modules\Auditoria\Domain\Contracts\RegistroDeExportaciones;
use App\Modules\Auditoria\Infrastructure\Persistence\Models\AuditoriaModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class RegistroDeExportacionesEloquent implements RegistroDeExportaciones
{
    public function registrar(
        string $entidadTipo,
        array $filtros,
        int $totalFilas,
        ?int $proyectoId,
        ?int $mandanteId = null,
        ?int $usuarioId = null,
        bool $completa = true,
    ): void {
        if ($mandanteId === null && $proyectoId !== null) {
            $id = DB::table('proyectos')->where('id', $proyectoId)->value('mandante_id');
            $mandanteId = $id === null ? null : (int) $id;
        }

        AuditoriaModel::query()->create([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoId,
            'mandante_id' => $mandanteId,
            'usuario_id' => $usuarioId ?? auth()->id(),
            'entidad_tipo' => $entidadTipo,
            // No hay UNA entidad exportada: es la tabla entera bajo un recorte.
            'entidad_id' => 0,
            'evento' => 'exportado',
            'datos_antes' => null,
            'datos_despues' => null,
            // Mismo formato que los cambios del observer, para que el detalle de
            // la pantalla de auditoría lo pinte sin un caso aparte.
            'cambios' => [
                'filtros' => ['antes' => null, 'despues' => $filtros],
                'total_filas' => ['antes' => null, 'despues' => $totalFilas],
                'completa' => ['antes' => null, 'despues' => $completa],
            ],
            'ip' => request()->ip(),
            'user_agent' => mb_substr((string) request()->userAgent(), 0, 512) ?: null,
        ]);
    }
}
