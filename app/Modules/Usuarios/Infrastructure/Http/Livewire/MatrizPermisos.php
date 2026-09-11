<?php

declare(strict_types=1);

namespace App\Modules\Usuarios\Infrastructure\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
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

    public string $busqueda = '';

    public bool $soloDiferencias = false;

    #[On('roles-actualizados')]
    public function actualizar(): void {}

    public function render(): View
    {
        abort_unless(auth()->user()?->esAdminGlobal() === true, 403);
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
        if (trim($this->busqueda) !== '') {
            $like = '%'.trim($this->busqueda).'%';
            $queryPermisos->where(fn ($q) => $q->where('nombre', 'like', $like)->orWhere('codigo', 'like', $like)->orWhere('grupo', 'like', $like));
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

        $excepciones = DB::table('rol_proyecto_permiso')->where('proyecto_id', $proyectoId)
            ->whereIn('rol_id', $rolesBase->pluck('id')->all())->get();
        foreach ($excepciones as $excepcion) {
            $ids = $rolPermisoBase->get($excepcion->rol_id, []);
            $id = (int) $excepcion->permiso_id;
            $rolPermisoBase->put($excepcion->rol_id, (bool) $excepcion->permitido
                ? array_values(array_unique([...$ids, $id]))
                : array_values(array_diff($ids, [$id])));
        }

        $rolPermisoCustom = DB::table('rol_custom_permiso')
            ->whereIn('rol_custom_id', $rolesCustom->pluck('id')->all())
            ->select(['rol_custom_id', 'permiso_id'])
            ->get()
            ->groupBy('rol_custom_id')
            ->map(fn ($filas) => $filas->pluck('permiso_id')->map(fn ($v) => (int) $v)->all());

        if ($this->soloDiferencias) {
            $permisos = $permisos->filter(function ($permiso) use ($rolesBase, $rolesCustom, $rolPermisoBase, $rolPermisoCustom): bool {
                $valores = [];
                foreach ($rolesBase as $rol) {
                    $valores[] = in_array((int) $permiso->id, $rolPermisoBase->get($rol->id, []), true);
                }
                foreach ($rolesCustom as $rol) {
                    $valores[] = in_array((int) $permiso->id, $rolPermisoCustom->get($rol->id, []), true);
                }

                return in_array(true, $valores, true) && in_array(false, $valores, true);
            });
        }

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
