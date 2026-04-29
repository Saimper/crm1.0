<?php

declare(strict_types=1);

namespace App\Modules\Usuarios\Infrastructure\Http\Livewire;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Gestión de roles por proyecto para SUPERVISOR (y ADMIN_GLOBAL via Gate::before).
 * Scoped al proyecto activo. Solo maneja los roles SUPERVISOR/GESTOR/AUDITOR.
 * Protecciones:
 *   - No mostrar ni modificar usuarios con rol ADMIN_GLOBAL.
 *   - No permitir auto-revocarse.
 *   - Validar que el usuario existe antes de asignar.
 */
final class GestionUsuariosProyecto extends Component
{
    public bool $formAsignarVisible = false;

    public string $buscarEmail = '';

    public ?int $usuarioBuscadoId = null;

    public string $usuarioBuscadoNombre = '';

    public ?int $rolAsignarId = null;

    /** @var list<int> IDs de carteras que restringen el rol (vacío = todo el proyecto). */
    public array $carterasSeleccionadas = [];

    public function abrirFormAsignar(): void
    {
        $this->formAsignarVisible = true;
        $this->buscarEmail = '';
        $this->usuarioBuscadoId = null;
        $this->usuarioBuscadoNombre = '';
        $this->rolAsignarId = null;
        $this->carterasSeleccionadas = [];
        $this->resetErrorBag();
    }

    public function cerrarFormAsignar(): void
    {
        $this->formAsignarVisible = false;
        $this->usuarioBuscadoId = null;
        $this->usuarioBuscadoNombre = '';
        $this->carterasSeleccionadas = [];
        $this->resetErrorBag();
    }

    public function buscarUsuario(): void
    {
        $email = strtolower(trim($this->buscarEmail));
        if ($email === '') {
            $this->addError('buscarEmail', 'Ingresa un correo.');
            return;
        }

        $user = User::query()->where('email', $email)->first();
        if ($user === null) {
            $this->addError('buscarEmail', 'No existe un usuario con ese correo.');
            $this->usuarioBuscadoId = null;
            $this->usuarioBuscadoNombre = '';
            return;
        }

        if ($user->esAdminGlobal()) {
            $this->addError('buscarEmail', 'Este usuario es ADMIN_GLOBAL; no se gestiona desde aquí.');
            $this->usuarioBuscadoId = null;
            $this->usuarioBuscadoNombre = '';
            return;
        }

        $this->usuarioBuscadoId = (int) $user->id;
        $this->usuarioBuscadoNombre = (string) $user->name;
    }

    public function asignar(): void
    {
        $this->validate([
            'usuarioBuscadoId' => ['required', 'integer', 'exists:users,id'],
            'rolAsignarId'     => ['required', 'integer', 'exists:roles,id'],
        ], [], [
            'usuarioBuscadoId' => 'usuario',
            'rolAsignarId'     => 'rol',
        ]);

        $rol = DB::table('roles')->where('id', $this->rolAsignarId)->first();
        if ($rol === null || ! in_array($rol->codigo, ['SUPERVISOR', 'GESTOR', 'AUDITOR'], true)) {
            $this->addError('rolAsignarId', 'Rol no válido para asignación por proyecto.');
            return;
        }

        $proyectoId = $this->proyectoActivoId();
        $usuarioId = (int) $this->usuarioBuscadoId;
        $rolId = (int) $this->rolAsignarId;

        DB::table('usuario_proyecto_rol')->upsert(
            [[
                'usuario_id'  => $usuarioId,
                'proyecto_id' => $proyectoId,
                'rol_id'      => $rolId,
                'equipo_id'   => null,
                'activo'      => true,
            ]],
            ['usuario_id', 'proyecto_id', 'rol_id'],
            ['equipo_id', 'activo'],
        );

        // Sincroniza restricción por cartera: reemplaza el conjunto actual.
        DB::table('usuario_proyecto_rol_cartera')
            ->where('usuario_id', $usuarioId)
            ->where('proyecto_id', $proyectoId)
            ->where('rol_id', $rolId)
            ->delete();

        $carterasValidas = DB::table('carteras')
            ->where('proyecto_id', $proyectoId)
            ->whereIn('id', $this->carterasSeleccionadas)
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->all();

        if ($carterasValidas !== []) {
            $filas = array_map(
                fn (int $cid) => [
                    'usuario_id'  => $usuarioId,
                    'proyecto_id' => $proyectoId,
                    'rol_id'      => $rolId,
                    'cartera_id'  => $cid,
                ],
                $carterasValidas,
            );
            DB::table('usuario_proyecto_rol_cartera')->insert($filas);
        }

        $this->cerrarFormAsignar();
        session()->flash('gestion-usuarios-ok', 'Rol asignado.');
    }

    public function quitar(int $usuarioId, int $rolId): void
    {
        // Protección: no auto-revocarse.
        if ($usuarioId === (int) auth()->id()) {
            session()->flash('gestion-usuarios-error', 'No puedes quitarte a ti mismo un rol en este proyecto.');
            return;
        }

        // Protección: el usuario objetivo no puede ser ADMIN_GLOBAL (por si acaso llega un id falso).
        /** @var User|null $target */
        $target = User::query()->find($usuarioId);
        if ($target === null || $target->esAdminGlobal()) {
            session()->flash('gestion-usuarios-error', 'No se puede modificar este usuario desde aquí.');
            return;
        }

        DB::table('usuario_proyecto_rol')
            ->where('usuario_id', $usuarioId)
            ->where('proyecto_id', $this->proyectoActivoId())
            ->where('rol_id', $rolId)
            ->delete();

        session()->flash('gestion-usuarios-ok', 'Rol removido.');
    }

    public function render(): View
    {
        $proyectoId = $this->proyectoActivoId();

        $asignaciones = DB::table('usuario_proyecto_rol as upr')
            ->join('users as u',  'u.id',  '=', 'upr.usuario_id')
            ->join('roles as r',  'r.id',  '=', 'upr.rol_id')
            ->leftJoin('usuario_global_rol as ugr', function ($j): void {
                $j->on('ugr.usuario_id', '=', 'u.id');
            })
            ->leftJoin('roles as rg', function ($j): void {
                $j->on('rg.id', '=', 'ugr.rol_id')->where('rg.codigo', 'ADMIN_GLOBAL');
            })
            ->where('upr.proyecto_id', $proyectoId)
            ->where('upr.activo', true)
            ->whereIn('r.codigo', ['SUPERVISOR', 'GESTOR', 'AUDITOR'])
            ->select([
                'upr.usuario_id', 'upr.rol_id', 'upr.equipo_id',
                'u.name', 'u.email', 'u.activo as usuario_activo',
                'r.codigo as rol_codigo', 'r.nombre as rol_nombre',
                DB::raw('max(case when rg.codigo = "ADMIN_GLOBAL" then 1 else 0 end) as es_admin_global'),
            ])
            ->groupBy(
                'upr.usuario_id', 'upr.rol_id', 'upr.equipo_id',
                'u.name', 'u.email', 'u.activo',
                'r.codigo', 'r.nombre',
            )
            ->orderBy('u.name')
            ->get()
            ->filter(fn (object $a): bool => ! (bool) $a->es_admin_global)
            ->groupBy('usuario_id');

        $rolesAsignables = DB::table('roles')
            ->where('activo', true)
            ->whereIn('codigo', ['SUPERVISOR', 'GESTOR', 'AUDITOR'])
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre']);

        $carterasDelProyecto = DB::table('carteras')
            ->where('proyecto_id', $proyectoId)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre']);

        // Mapa (usuario_id, rol_id) → carteras restringidas (vacío = todo el proyecto)
        $restricciones = DB::table('usuario_proyecto_rol_cartera as rc')
            ->join('carteras as c', 'c.id', '=', 'rc.cartera_id')
            ->where('rc.proyecto_id', $proyectoId)
            ->select(['rc.usuario_id', 'rc.rol_id', 'c.nombre as cartera_nombre', 'c.id as cartera_id'])
            ->get()
            ->groupBy(fn ($r) => $r->usuario_id.'-'.$r->rol_id);

        return view('usuarios::admin.gestion-usuarios-proyecto', [
            'asignaciones'        => $asignaciones,
            'rolesAsignables'     => $rolesAsignables,
            'carterasDelProyecto' => $carterasDelProyecto,
            'restricciones'       => $restricciones,
            'usuarioActualId'     => (int) auth()->id(),
        ]);
    }

    private function proyectoActivoId(): int
    {
        return (int) app('tenancy.proyecto_activo')->id;
    }
}
