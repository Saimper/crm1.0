<?php

declare(strict_types=1);

namespace App\Modules\Usuarios\Infrastructure\Persistence\Repositories;

use App\Models\User;
use App\Modules\Usuarios\Domain\Contracts\AccesoAReparto;
use Illuminate\Support\Facades\DB;

final class AccesoARepartoEloquent implements AccesoAReparto
{
    public function puedeRepartir(int $usuarioId, int $proyectoId, ?int $carteraId = null): bool
    {
        return $this->usuario($usuarioId, $proyectoId)?->tienePermiso('asignaciones.reasignar', $proyectoId, $carteraId) === true;
    }

    public function puedeRecibir(int $usuarioId, int $proyectoId, int $carteraId): bool
    {
        $usuario = $this->usuario($usuarioId, $proyectoId);

        return $usuario !== null && ! $usuario->esAdminGlobal()
            && $usuario->tienePermiso('casos.ver', $proyectoId, $carteraId)
            && $usuario->tienePermiso('gestiones.crear', $proyectoId, $carteraId);
    }

    private function usuario(int $usuarioId, int $proyectoId): ?User
    {
        $proyecto = DB::table('proyectos as p')->join('mandantes as m', 'm.id', '=', 'p.mandante_id')
            ->where('p.id', $proyectoId)->where('p.activo', true)->whereNull('p.eliminada_en')
            ->where('m.activo', true)->whereNull('m.eliminada_en')->exists();
        if (! $proyecto) {
            return null;
        }
        $usuario = User::query()->whereKey($usuarioId)->where('activo', true)->first();

        return $usuario !== null && $usuario->tieneAccesoAProyecto($proyectoId) ? $usuario : null;
    }
}
