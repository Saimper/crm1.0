<?php

declare(strict_types=1);

namespace App\Modules\Usuarios\Infrastructure\Persistence\Repositories;

use App\Models\User;
use App\Modules\Usuarios\Domain\Contracts\AccesoACuenta;
use App\Support\Database\CarterasOperativas;
use Illuminate\Support\Facades\DB;

final class AccesoACuentaEloquent implements AccesoACuenta
{
    public function puedeImportar(int $usuarioId, int $proyectoId, ?int $carteraId = null): bool
    {
        $usuario = $this->usuarioDisponible($usuarioId, $proyectoId);

        return $usuario !== null
            && ($carteraId === null || CarterasOperativas::carteraDisponible(DB::connection(), $proyectoId, $carteraId))
            && $usuario->tienePermiso('importaciones.procesar', $proyectoId, $carteraId);
    }

    public function puedeGestionar(int $usuarioId, int $proyectoId, int $casoId): bool
    {
        $usuario = $this->usuarioDisponible($usuarioId, $proyectoId);
        if ($usuario === null) {
            return false;
        }
        $caso = CarterasOperativas::casos(DB::connection(), $proyectoId)
            ->join('personas as pe', fn ($join) => $join->on('pe.id', '=', 'c.persona_id')->on('pe.proyecto_id', '=', 'c.proyecto_id'))
            ->whereNull('pe.eliminada_en')->where('c.id', $casoId)->first(['c.id', 'c.cartera_id']);
        if ($caso === null || ! $usuario->tienePermiso('gestiones.crear', $proyectoId, (int) $caso->cartera_id)) {
            return false;
        }
        // A closed assignment retains its owner; cooperation never transfers it.
        $duenio = DB::table('asignaciones')->where('proyecto_id', $proyectoId)->where('caso_id', $casoId)->value('usuario_id');

        return $duenio === null || (int) $duenio === $usuarioId
            || $usuario->tienePermiso('casos.colaborar', $proyectoId, (int) $caso->cartera_id);
    }

    public function puedeReincorporar(int $usuarioId, int $proyectoId, int $carteraOrigenId, int $carteraDestinoId): bool
    {
        $usuario = $this->usuarioDisponible($usuarioId, $proyectoId);
        if ($usuario === null || $carteraOrigenId === $carteraDestinoId
            || ! CarterasOperativas::carteraDisponible(DB::connection(), $proyectoId, $carteraDestinoId)) {
            return false;
        }
        $origen = DB::table('carteras')->where('proyecto_id', $proyectoId)->where('id', $carteraOrigenId)
            ->where(fn ($q) => $q->where('activo', false)->orWhereNotNull('eliminada_en'))->exists();
        if (! $origen) {
            return false;
        }
        foreach ([$carteraOrigenId, $carteraDestinoId] as $carteraId) {
            // Receiving an archived debt is an import action, not a portfolio metadata edit.
            // Source scope still matters: moving it also makes its history available in the destination.
            if (! $usuario->tienePermiso('importaciones.procesar', $proyectoId, $carteraId)) {
                return false;
            }
        }

        return true;
    }

    private function usuarioDisponible(int $usuarioId, int $proyectoId): ?User
    {
        $activo = DB::table('proyectos as p')->join('mandantes as m', 'm.id', '=', 'p.mandante_id')
            ->where('p.id', $proyectoId)->where('p.activo', true)->whereNull('p.eliminada_en')
            ->where('m.activo', true)->whereNull('m.eliminada_en')->exists();
        if (! $activo) {
            return null;
        }
        $usuario = User::query()->whereKey($usuarioId)->where('activo', true)->first();

        return $usuario !== null && $usuario->tieneAccesoAProyecto($proyectoId) ? $usuario : null;
    }
}
