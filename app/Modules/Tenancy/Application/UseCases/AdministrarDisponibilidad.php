<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application\UseCases;

use App\Modules\Auditoria\Domain\Contracts\RegistroDeAccionesAdministrativas;
use Illuminate\Database\ConnectionInterface;

final readonly class AdministrarDisponibilidad
{
    public function __construct(
        private ConnectionInterface $db,
        private RegistroDeAccionesAdministrativas $auditoria,
    ) {}

    public function eliminarMandante(int $mandanteId): void
    {
        $this->db->transaction(function () use ($mandanteId): void {
            $mandante = $this->db->table('mandantes')->where('id', $mandanteId)
                ->whereNull('eliminada_en')->lockForUpdate()->first();
            if ($mandante === null) {
                return;
            }

            foreach ($this->db->table('proyectos')->where('mandante_id', $mandanteId)
                ->whereNull('eliminada_en')->orderBy('id')->pluck('id') as $id) {
                $this->eliminarProyecto((int) $id);
            }
            $this->db->table('mandantes')->where('id', $mandanteId)
                ->update(['activo' => false, 'eliminada_en' => now(), 'actualizada_en' => now()]);
            $this->auditoria->baja('mandantes', $mandanteId, ['codigo' => $mandante->codigo], mandanteId: $mandanteId);
        });
    }

    public function eliminarProyecto(int $proyectoId): void
    {
        $this->db->transaction(function () use ($proyectoId): void {
            $proyecto = $this->db->table('proyectos')->where('id', $proyectoId)
                ->whereNull('eliminada_en')->lockForUpdate()->first();
            if ($proyecto === null) {
                return;
            }
            // Queued work also checks portfolio availability outside HTTP middleware.
            $this->db->table('carteras')->where('proyecto_id', $proyectoId)->whereNull('eliminada_en')
                ->update(['activo' => false, 'actualizada_en' => now()]);
            $this->db->table('proyectos')->where('id', $proyectoId)
                ->update(['activo' => false, 'eliminada_en' => now(), 'actualizada_en' => now()]);
            $this->auditoria->baja('proyectos', $proyectoId, ['codigo' => $proyecto->codigo],
                proyectoId: $proyectoId, mandanteId: (int) $proyecto->mandante_id);
        });
    }

    public function eliminarCartera(int $proyectoId, int $carteraId): void
    {
        $this->cambiarCartera($proyectoId, $carteraId, false, true);
    }

    public function cambiarEstadoCartera(int $proyectoId, int $carteraId, bool $activo): void
    {
        $this->cambiarCartera($proyectoId, $carteraId, $activo, false);
    }

    private function cambiarCartera(int $proyectoId, int $carteraId, bool $activo, bool $eliminar): void
    {
        $this->db->transaction(function () use ($proyectoId, $carteraId, $activo, $eliminar): void {
            $cartera = $this->db->table('carteras')->where('proyecto_id', $proyectoId)
                ->where('id', $carteraId)->whereNull('eliminada_en')->lockForUpdate()->first();
            if ($cartera === null) {
                return;
            }
            $this->db->table('carteras')->where('proyecto_id', $proyectoId)->where('id', $carteraId)
                ->update(['activo' => $activo, 'eliminada_en' => $eliminar ? now() : null, 'actualizada_en' => now()]);
            if ($eliminar) {
                $this->auditoria->baja('carteras', $carteraId, ['codigo' => $cartera->codigo], proyectoId: $proyectoId);
            } elseif ((bool) $cartera->activo !== $activo) {
                $this->auditoria->cambio('carteras', $carteraId,
                    ['activo' => ['antes' => (bool) $cartera->activo, 'despues' => $activo]], proyectoId: $proyectoId);
            }
        });
    }
}
