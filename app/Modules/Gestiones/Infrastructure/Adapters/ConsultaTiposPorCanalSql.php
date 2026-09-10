<?php

declare(strict_types=1);

namespace App\Modules\Gestiones\Infrastructure\Adapters;

use App\Modules\Gestiones\Domain\Contracts\ConsultaTiposPorCanal;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

final readonly class ConsultaTiposPorCanalSql implements ConsultaTiposPorCanal
{
    public function __construct(private ConnectionInterface $db) {}

    public function idsAdmitidos(int $proyectoId, int $canalId): array
    {
        $activo = $this->db->table('canal_proyecto as cp')->join('canales as c', 'c.id', '=', 'cp.canal_id')
            ->where('cp.proyecto_id', $proyectoId)->where('cp.canal_id', $canalId)
            ->where('cp.activo', true)->where('c.activo', true)->exists();
        if (! $activo) {
            return [];
        }

        // Unrestricted existing types keep working until channels are configured.
        $vinculos = fn (Builder $q) => $q->selectRaw('1')->from('canal_tipo_gestion as ctg')
            ->where('ctg.proyecto_id', $proyectoId)->whereColumn('ctg.tipo_gestion_id', 'tg.id');

        return $this->db->table('tipos_gestion as tg')->where('tg.proyecto_id', $proyectoId)->where('tg.activo', true)
            ->where(fn (Builder $q) => $q->whereNotExists($vinculos)
                ->orWhereExists(fn (Builder $q) => $vinculos($q)->where('ctg.canal_id', $canalId)))
            ->orderBy('tg.orden')->orderBy('tg.id')->pluck('tg.id')->map(fn ($id): int => (int) $id)->all();
    }
}
