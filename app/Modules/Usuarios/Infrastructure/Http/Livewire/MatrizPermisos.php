<?php

declare(strict_types=1);

namespace App\Modules\Usuarios\Infrastructure\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Visualizador read-only: matriz permisos × roles (base + custom del proyecto).
 *
 * Útil para auditoría/cumplimiento. ADMIN_GLOBAL exclusivo (Gate::before
 * cubre `roles.gestionar`).
 */
final class MatrizPermisos extends Component
{
    public string $filtroGrupo = '';

    public function render(): View
    {
        $proyectoId = $this->proyectoActivoId();

        $rolesBase = DB::table('roles')
            ->where('activo', true)
            ->whereIn('codigo', ['SUPERVISOR', 'GESTOR', 'AUDITOR'])
            ->orderBy('orden')
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre']);

        $rolesCustom = DB::table('roles_custom')
            ->where('proyecto_id', $proyectoId)
            ->where('activo', true)
            ->whereNull('eliminada_en')
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre']);

        $queryPermisos = DB::table('permisos')
            ->where('activo', true);

        if ($this->filtroGrupo !== '') {
            $queryPermisos->where('grupo', $this->filtroGrupo);
        }

        $permisos = $queryPermisos
            ->orderBy('grupo')
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre', 'grupo']);

        $rolPermisoBase = DB::table('rol_permiso')
            ->whereIn('rol_id', $rolesBase->pluck('id')->all())
            ->select(['rol_id', 'permiso_id'])
            ->get()
            ->groupBy('rol_id')
            ->map(fn ($filas) => $filas->pluck('permiso_id')->map(fn ($v) => (int) $v)->all());

        $rolPermisoCustom = DB::table('rol_custom_permiso')
            ->whereIn('rol_custom_id', $rolesCustom->pluck('id')->all())
            ->select(['rol_custom_id', 'permiso_id'])
            ->get()
            ->groupBy('rol_custom_id')
            ->map(fn ($filas) => $filas->pluck('permiso_id')->map(fn ($v) => (int) $v)->all());

        $grupos = DB::table('permisos')
            ->where('activo', true)
            ->select('grupo')
            ->distinct()
            ->orderBy('grupo')
            ->pluck('grupo')
            ->all();

        return view('usuarios::admin.matriz-permisos', [
            'rolesBase' => $rolesBase,
            'rolesCustom' => $rolesCustom,
            'permisos' => $permisos,
            'rolPermisoBase' => $rolPermisoBase,
            'rolPermisoCustom' => $rolPermisoCustom,
            'grupos' => $grupos,
        ]);
    }

    private function proyectoActivoId(): int
    {
        return (int) app('tenancy.proyecto_activo')->id;
    }
}
