<?php

declare(strict_types=1);

namespace App\Modules\Usuarios\Infrastructure\Http\Livewire;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

/**
 * Admin de usuarios globales + sus asignaciones por proyecto. Solo ADMIN_GLOBAL.
 * Operaciones:
 *   - Crear / editar / desactivar usuarios.
 *   - Promover o revocar rol ADMIN_GLOBAL (inserta/elimina en usuario_global_rol).
 *   - Asignar o quitar un rol por proyecto (inserta/elimina en usuario_proyecto_rol).
 */
final class AdminUsuarios extends Component
{
    public bool $formUsuarioVisible = false;

    public ?int $editandoUsuarioId = null;

    public string $busqueda = '';

    /** @var array<string, mixed> */
    public array $formUsuario = [
        'name' => '',
        'email' => '',
        'password' => '',
        'activo' => true,
    ];

    public bool $formAsignacionVisible = false;

    public ?int $usuarioAsignandoId = null;

    public ?int $asignarProyectoId = null;

    public ?int $asignarRolId = null;

    public function abrirFormCrearUsuario(): void
    {
        $this->editandoUsuarioId = null;
        $this->formUsuario = ['name' => '', 'email' => '', 'password' => '', 'activo' => true];
        $this->formUsuarioVisible = true;
        $this->resetErrorBag();
    }

    public function abrirFormEditarUsuario(int $id): void
    {
        $u = User::query()->find($id);
        if ($u === null) {
            return;
        }
        $this->editandoUsuarioId = $id;
        $this->formUsuario = [
            'name' => $u->name,
            'email' => $u->email,
            'password' => '',
            'activo' => (bool) ($u->activo ?? true),
        ];
        $this->formUsuarioVisible = true;
        $this->resetErrorBag();
    }

    public function cerrarFormUsuario(): void
    {
        $this->formUsuarioVisible = false;
        $this->editandoUsuarioId = null;
        $this->resetErrorBag();
    }

    public function guardarUsuario(): void
    {
        $reglasPassword = $this->editandoUsuarioId === null
            ? ['required', 'string', 'min:8', 'max:72']
            : ['nullable', 'string', 'min:8', 'max:72'];

        $reglasEmail = $this->editandoUsuarioId === null
            ? ['required', 'email', 'max:255', 'unique:users,email']
            : ['required', 'email', 'max:255', 'unique:users,email,'.$this->editandoUsuarioId];

        $this->validate([
            'formUsuario.name' => ['required', 'string', 'max:255'],
            'formUsuario.email' => $reglasEmail,
            'formUsuario.password' => $reglasPassword,
            'formUsuario.activo' => ['boolean'],
        ], [], [
            'formUsuario.name' => 'nombre',
            'formUsuario.email' => 'correo',
            'formUsuario.password' => 'contraseña',
        ]);

        if ($this->editandoUsuarioId === null) {
            User::query()->create([
                'name' => (string) $this->formUsuario['name'],
                'email' => strtolower((string) $this->formUsuario['email']),
                'password' => Hash::make((string) $this->formUsuario['password']),
                'activo' => (bool) ($this->formUsuario['activo'] ?? true),
            ]);
        } else {
            $u = User::query()->findOrFail($this->editandoUsuarioId);
            $u->name = (string) $this->formUsuario['name'];
            $u->email = strtolower((string) $this->formUsuario['email']);
            $u->activo = (bool) ($this->formUsuario['activo'] ?? true);
            if (! empty($this->formUsuario['password'])) {
                $u->password = Hash::make((string) $this->formUsuario['password']);
            }
            $u->save();
        }

        $this->cerrarFormUsuario();
        session()->flash('admin-usuarios-ok', 'Usuario guardado.');
    }

    public function promoverAdminGlobal(int $usuarioId): void
    {
        $this->soloAdminGlobal();

        $rolAdminGlobalId = (int) DB::table('roles')->where('codigo', 'ADMIN_GLOBAL')->value('id');
        if ($rolAdminGlobalId === 0) {
            return;
        }

        $existe = DB::table('usuario_global_rol')
            ->where('usuario_id', $usuarioId)
            ->where('rol_id', $rolAdminGlobalId)
            ->exists();
        if ($existe) {
            return;
        }

        DB::table('usuario_global_rol')->insert([
            'usuario_id' => $usuarioId,
            'rol_id' => $rolAdminGlobalId,
        ]);
        session()->flash('admin-usuarios-ok', 'Usuario promovido a ADMIN_GLOBAL.');
    }

    public function revocarAdminGlobal(int $usuarioId): void
    {
        $this->soloAdminGlobal();

        $rolAdminGlobalId = (int) DB::table('roles')->where('codigo', 'ADMIN_GLOBAL')->value('id');
        if ($rolAdminGlobalId === 0) {
            return;
        }

        // Protección: evitar que el admin se revoque a sí mismo.
        if ($usuarioId === (int) auth()->id()) {
            session()->flash('admin-usuarios-error', 'No puedes revocar tu propio rol ADMIN_GLOBAL.');

            return;
        }

        // Protección: no dejar el sistema sin admins globales.
        $otrosAdmin = DB::table('usuario_global_rol')
            ->where('rol_id', $rolAdminGlobalId)
            ->where('usuario_id', '!=', $usuarioId)
            ->exists();
        if (! $otrosAdmin) {
            session()->flash('admin-usuarios-error', 'No se puede revocar al último ADMIN_GLOBAL del sistema.');

            return;
        }

        DB::table('usuario_global_rol')
            ->where('usuario_id', $usuarioId)
            ->where('rol_id', $rolAdminGlobalId)
            ->delete();
        session()->flash('admin-usuarios-ok', 'Rol ADMIN_GLOBAL revocado.');
    }

    public function abrirFormAsignacion(int $usuarioId): void
    {
        $this->usuarioAsignandoId = $usuarioId;
        $this->asignarProyectoId = null;
        $this->asignarRolId = null;
        $this->formAsignacionVisible = true;
        $this->resetErrorBag();
    }

    public function cerrarFormAsignacion(): void
    {
        $this->formAsignacionVisible = false;
        $this->usuarioAsignandoId = null;
        $this->asignarProyectoId = null;
        $this->asignarRolId = null;
        $this->resetErrorBag();
    }

    public function guardarAsignacion(): void
    {
        $this->validate([
            'usuarioAsignandoId' => ['required', 'integer', 'exists:users,id'],
            'asignarProyectoId' => ['required', 'integer', 'exists:proyectos,id'],
            'asignarRolId' => ['required', 'integer', 'exists:roles,id'],
        ], [], [
            'asignarProyectoId' => 'proyecto',
            'asignarRolId' => 'rol',
        ]);

        $this->guardContraProyectoAjeno((int) $this->asignarProyectoId);

        // Un usuario puede tener múltiples roles en el mismo proyecto (PK compuesta usuario+proyecto+rol).
        // Upsert para reactivar si ya existía en inactivo.
        DB::table('usuario_proyecto_rol')->upsert(
            [[
                'usuario_id' => (int) $this->usuarioAsignandoId,
                'proyecto_id' => (int) $this->asignarProyectoId,
                'rol_id' => (int) $this->asignarRolId,
                'equipo_id' => null,
                'activo' => true,
            ]],
            ['usuario_id', 'proyecto_id', 'rol_id'],
            ['equipo_id', 'activo'],
        );

        $this->cerrarFormAsignacion();
        session()->flash('admin-usuarios-ok', 'Asignación guardada.');
    }

    public function quitarAsignacion(int $usuarioId, int $proyectoId, int $rolId): void
    {
        $this->guardContraProyectoAjeno($proyectoId);

        DB::table('usuario_proyecto_rol')
            ->where('usuario_id', $usuarioId)
            ->where('proyecto_id', $proyectoId)
            ->where('rol_id', $rolId)
            ->delete();
        session()->flash('admin-usuarios-ok', 'Asignación removida.');
    }

    public function render(): View
    {
        $busqueda = trim($this->busqueda);

        $mandantesPermitidos = $this->mandantesPermitidos();
        $proyectosPermitidos = $mandantesPermitidos === null
            ? null
            : $this->proyectosDeMandantes($mandantesPermitidos);

        $queryUsuarios = DB::table('users as u')
            ->leftJoin('usuario_global_rol as ugr', 'ugr.usuario_id', '=', 'u.id')
            ->leftJoin('roles as rg', function ($j): void {
                $j->on('rg.id', '=', 'ugr.rol_id')->where('rg.codigo', 'ADMIN_GLOBAL');
            });

        if ($busqueda !== '') {
            $like = '%'.$busqueda.'%';
            $queryUsuarios->where(function ($q) use ($like): void {
                $q->where('u.name', 'like', $like)
                    ->orWhere('u.email', 'like', $like);
            });
        }

        // F39: admin_mandante ve solo usuarios con pivot en sus proyectos
        // (o pivot mandante en su mandante).
        if ($proyectosPermitidos !== null) {
            $queryUsuarios->where(function ($q) use ($proyectosPermitidos, $mandantesPermitidos): void {
                $q->whereExists(function ($sub) use ($proyectosPermitidos): void {
                    $sub->select(DB::raw(1))
                        ->from('usuario_proyecto_rol')
                        ->whereColumn('usuario_proyecto_rol.usuario_id', 'u.id')
                        ->whereIn('usuario_proyecto_rol.proyecto_id', $proyectosPermitidos);
                })->orWhereExists(function ($sub) use ($mandantesPermitidos): void {
                    $sub->select(DB::raw(1))
                        ->from('usuario_mandante_rol')
                        ->whereColumn('usuario_mandante_rol.usuario_id', 'u.id')
                        ->whereIn('usuario_mandante_rol.mandante_id', $mandantesPermitidos);
                });
            });
        }

        $usuarios = $queryUsuarios
            ->select([
                'u.id', 'u.name', 'u.email', 'u.activo',
                DB::raw('max(case when rg.codigo = "ADMIN_GLOBAL" then 1 else 0 end) as es_admin_global'),
            ])
            ->groupBy('u.id', 'u.name', 'u.email', 'u.activo')
            ->orderBy('u.name')
            ->get();

        $queryAsignaciones = DB::table('usuario_proyecto_rol as upr')
            ->join('proyectos as p', 'p.id', '=', 'upr.proyecto_id')
            ->join('roles as r', 'r.id', '=', 'upr.rol_id');

        if ($proyectosPermitidos !== null) {
            $queryAsignaciones->whereIn('upr.proyecto_id', $proyectosPermitidos);
        }

        $asignaciones = $queryAsignaciones
            ->select([
                'upr.usuario_id', 'upr.proyecto_id', 'upr.rol_id', 'upr.activo',
                'p.codigo as proyecto_codigo', 'p.nombre as proyecto_nombre',
                'p.tipo_operacion',
                'r.codigo as rol_codigo', 'r.nombre as rol_nombre',
            ])
            ->orderBy('p.codigo')
            ->orderBy('r.codigo')
            ->get()
            ->groupBy('usuario_id');

        $queryProyectos = DB::table('proyectos')
            ->whereNull('eliminada_en')
            ->orderBy('codigo');

        if ($mandantesPermitidos !== null) {
            $queryProyectos->whereIn('mandante_id', $mandantesPermitidos);
        }

        $proyectos = $queryProyectos->get(['id', 'codigo', 'nombre']);

        $roles = DB::table('roles')
            ->where('activo', true)
            ->whereIn('codigo', ['SUPERVISOR', 'GESTOR', 'AUDITOR'])    // ADMIN_GLOBAL se maneja aparte.
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre']);

        return view('usuarios::admin.lista', [
            'usuarios' => $usuarios,
            'asignaciones' => $asignaciones,
            'proyectos' => $proyectos,
            'roles' => $roles,
            'usuarioActual' => auth()->id(),
        ]);
    }

    /** @return list<int>|null */
    private function mandantesPermitidos(): ?array
    {
        $usuario = auth()->user();
        if ($usuario === null || $usuario->esAdminGlobal()) {
            return null;
        }

        return $usuario->mandantesAdministrados();
    }

    /**
     * @param  list<int>  $mandantes
     * @return list<int>
     */
    private function proyectosDeMandantes(array $mandantes): array
    {
        if ($mandantes === []) {
            return [];
        }

        return DB::table('proyectos')
            ->whereIn('mandante_id', $mandantes)
            ->whereNull('eliminada_en')
            ->pluck('id')
            ->map(fn (mixed $v): int => (int) $v)
            ->all();
    }

    private function soloAdminGlobal(): void
    {
        $u = auth()->user();
        if ($u === null || ! $u->esAdminGlobal()) {
            abort(403, 'Solo ADMIN_GLOBAL puede gestionar el rol global.');
        }
    }

    private function guardContraProyectoAjeno(int $proyectoId): void
    {
        $mandantes = $this->mandantesPermitidos();
        if ($mandantes === null) {
            return;
        }
        $proyectos = $this->proyectosDeMandantes($mandantes);
        if (! in_array($proyectoId, $proyectos, true)) {
            abort(403, 'No puedes operar usuarios en proyectos de otro mandante.');
        }
    }
}
