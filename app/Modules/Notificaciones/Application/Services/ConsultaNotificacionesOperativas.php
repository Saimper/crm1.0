<?php

declare(strict_types=1);

namespace App\Modules\Notificaciones\Application\Services;

use App\Support\Database\CarterasOperativas;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Hide archived account reminders without deleting the original notification. */
final class ConsultaNotificacionesOperativas
{
    public function paraUsuario(int $projectId, int $userId): Builder
    {
        return DB::table('notificaciones')
            ->where('proyecto_id', $projectId)
            ->where('destinatario_usuario_id', $userId)
            ->where(fn (Builder $query) => $query
                ->whereNotIn('entidad_tipo', ['compromiso', 'compromisos'])
                ->orWhereExists(fn (Builder $commitment) => CarterasOperativas::filtrarVinculados(
                    $commitment->selectRaw('1')->from('compromisos as notified_commitment')
                        ->whereColumn('notified_commitment.id', 'notificaciones.entidad_id')
                        ->whereColumn('notified_commitment.proyecto_id', 'notificaciones.proyecto_id')
                        ->whereNull('notified_commitment.eliminada_en'),
                    'notified_commitment',
                )))
            ->where(fn (Builder $query) => $query->whereNull('metadata->caso_id')
                ->orWhereExists(fn (Builder $case) => CarterasOperativas::filtrar(
                    $case->selectRaw('1')->from('casos as notified_metadata_case')
                        ->whereColumn('notified_metadata_case.id', 'notificaciones.metadata->caso_id')
                        ->whereColumn('notified_metadata_case.proyecto_id', 'notificaciones.proyecto_id')
                        ->whereNull('notified_metadata_case.eliminada_en'),
                    'notified_metadata_case',
                )));
    }
}
