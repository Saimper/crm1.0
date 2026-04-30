<?php

declare(strict_types=1);

namespace App\Modules\Usuarios\Infrastructure\Http\Livewire;

use App\Modules\Usuarios\Application\RolesCustom\DTOs\EntradaRolCustom;
use App\Modules\Usuarios\Application\RolesCustom\UseCases\ActualizarRolCustom;
use App\Modules\Usuarios\Application\RolesCustom\UseCases\CrearRolCustom;
use App\Modules\Usuarios\Application\RolesCustom\UseCases\EliminarRolCustom;
use App\Modules\Usuarios\Domain\RolesCustom\Entities\RolCustom;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Admin de roles custom por proyecto. Solo ADMIN_GLOBAL (Gate::before).
 *
 * Defensa en profundidad (patrón F23): cada acción mutadora invoca
 * `$this->authorize('roles.gestionar', $proyectoId)` aunque el middleware
 * `can:` ya filtró la entrada.
 */
final class AdminRolesCustom extends Component
{
    public bool $formVisible = false;

    public ?int $editandoId = null;

    public string $form_codigo = '';

    public string $form_nombre = '';

    public string $form_descripcion = '';

    /** @var list<string> */
    public array $form_permisos = [];

    public function abrirFormCrear(): void
    {
        $this->authorize('roles.gestionar', $this->proyectoActivoId());
        $this->editandoId = null;
        $this->form_codigo = '';
        $this->form_nombre = '';
        $this->form_descripcion = '';
        $this->form_permisos = [];
        $this->formVisible = true;
        $this->resetErrorBag();
    }

    public function abrirFormEditar(int $id): void
    {
        $this->authorize('roles.gestionar', $this->proyectoActivoId());

        $rol = DB::table('roles_custom')
            ->where('id', $id)
            ->where('proyecto_id', $this->proyectoActivoId())
            ->whereNull('eliminada_en')
            ->first();
        if ($rol === null) {
            session()->flash('roles-custom-error', 'Rol no encontrado.');

            return;
        }

        $permisos = DB::table('rol_custom_permiso as rcp')
            ->join('permisos as p', 'p.id', '=', 'rcp.permiso_id')
            ->where('rcp.rol_custom_id', $id)
            ->pluck('p.codigo')
            ->map(fn ($v) => (string) $v)
            ->values()
            ->all();

        $this->editandoId = $id;
        $this->form_codigo = (string) $rol->codigo;
        $this->form_nombre = (string) $rol->nombre;
        $this->form_descripcion = (string) ($rol->descripcion ?? '');
        $this->form_permisos = $permisos;
        $this->formVisible = true;
        $this->resetErrorBag();
    }

    public function cerrarForm(): void
    {
        $this->formVisible = false;
        $this->editandoId = null;
        $this->resetErrorBag();
    }

    public function guardar(CrearRolCustom $crear, ActualizarRolCustom $actualizar): void
    {
        $this->authorize('roles.gestionar', $this->proyectoActivoId());

        // Filtro defensivo final: aunque la UI no muestra permisos vetados,
        // si llegan vía payload manipulado se descartan antes del UseCase.
        $permisosLimpios = array_values(array_filter(
            array_unique($this->form_permisos),
            fn (string $codigo): bool => RolCustom::puedeAsignarPermiso($codigo),
        ));

        $entrada = new EntradaRolCustom(
            proyectoId: $this->proyectoActivoId(),
            codigo: $this->form_codigo,
            nombre: $this->form_nombre,
            descripcion: $this->form_descripcion === '' ? null : $this->form_descripcion,
            permisos: $permisosLimpios,
        );

        try {
            if ($this->editandoId === null) {
                $crear->execute($entrada, (int) auth()->id());
            } else {
                $actualizar->execute($this->editandoId, $entrada);
            }
        } catch (\Throwable $e) {
            $this->addError('form', $e->getMessage());

            return;
        }

        $this->cerrarForm();
        session()->flash('roles-custom-ok', 'Rol custom guardado.');
    }

    public function eliminar(int $id, EliminarRolCustom $useCase): void
    {
        $this->authorize('roles.gestionar', $this->proyectoActivoId());

        $rol = DB::table('roles_custom')
            ->where('id', $id)
            ->where('proyecto_id', $this->proyectoActivoId())
            ->whereNull('eliminada_en')
            ->first();
        if ($rol === null) {
            return;
        }

        try {
            $useCase->execute($id);
        } catch (\Throwable $e) {
            session()->flash('roles-custom-error', $e->getMessage());

            return;
        }

        session()->flash('roles-custom-ok', 'Rol custom eliminado.');
    }

    public function render(): View
    {
        $proyectoId = $this->proyectoActivoId();

        $rolesBase = DB::table('roles')
            ->where('activo', true)
            ->whereIn('codigo', ['SUPERVISOR', 'GESTOR', 'AUDITOR'])
            ->orderBy('orden')
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre', 'descripcion']);

        $rolesCustom = DB::table('roles_custom')
            ->where('proyecto_id', $proyectoId)
            ->whereNull('eliminada_en')
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre', 'descripcion', 'activo']);

        $conteoPermisos = DB::table('rol_custom_permiso')
            ->whereIn('rol_custom_id', $rolesCustom->pluck('id')->all())
            ->select('rol_custom_id', DB::raw('count(*) as total'))
            ->groupBy('rol_custom_id')
            ->pluck('total', 'rol_custom_id');

        $conteoAsignaciones = DB::table('usuario_proyecto_rol_custom')
            ->whereIn('rol_custom_id', $rolesCustom->pluck('id')->all())
            ->where('proyecto_id', $proyectoId)
            ->where('activo', true)
            ->select('rol_custom_id', DB::raw('count(distinct usuario_id) as total'))
            ->groupBy('rol_custom_id')
            ->pluck('total', 'rol_custom_id');

        $permisosDisponibles = DB::table('permisos')
            ->where('activo', true)
            ->whereNotIn('codigo', RolCustom::PERMISOS_VETADOS)
            ->orderBy('grupo')
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre', 'grupo'])
            ->groupBy('grupo');

        return view('usuarios::admin.roles-custom', [
            'rolesBase' => $rolesBase,
            'rolesCustom' => $rolesCustom,
            'conteoPermisos' => $conteoPermisos,
            'conteoAsignaciones' => $conteoAsignaciones,
            'permisosDisponibles' => $permisosDisponibles,
        ]);
    }

    private function proyectoActivoId(): int
    {
        return (int) app('tenancy.proyecto_activo')->id;
    }
}
