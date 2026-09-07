<?php

declare(strict_types=1);

namespace App\Modules\Usuarios\Infrastructure\Http\Livewire;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Locked;
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

    #[Locked]
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

    #[Locked]
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
        $this->guardContraUsuarioAjeno($id);

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

        if ($this->editandoUsuarioId !== null) {
            $this->guardContraUsuarioAjeno($this->editandoUsuarioId);
        }

        if ($this->editandoUsuarioId === null) {
            $nuevo = User::query()->create([
                'name' => (string) $this->formUsuario['name'],
                'email' => strtolower((string) $this->formUsuario['email']),
                'password' => Hash::make((string) $this->formUsuario['password']),
                'activo' => (bool) ($this->formUsuario['activo'] ?? true),
            ]);

            // Nace ligado al cliente en el que se está trabajando. Sin esto el
            // usuario quedaba huérfano y el propio filtro de la pantalla lo
            // escondía de quien acababa de crearlo.
            $this->ligarAlClienteActivo((int) $nuevo->id);
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
        $this->guardContraUsuarioDeOtroMandante($usuarioId);

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

        // El guard de proyecto no basta: con un proyecto propio deja pasar a
        // cualquier usuario. Así es como un empleado del cliente B acababa
        // dentro del proyecto del cliente A.
        $this->guardContraUsuarioDeOtroMandante((int) $this->usuarioAsignandoId);
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
        $this->guardContraUsuarioDeOtroMandante($usuarioId);
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
                // De qué cliente es cada fila. Sin esta columna la tabla mezclaba
                // empresas sin decirlo, que es justo cómo se borra a quien no toca.
                DB::raw('(select group_concat(distinct m.codigo order by m.codigo separator ", ")
                            from mandantes m
                           where m.id in (
                                 select p.mandante_id from usuario_proyecto_rol upr2
                                   join proyectos p on p.id = upr2.proyecto_id
                                  where upr2.usuario_id = u.id and upr2.activo = 1
                                 union
                                 select umr2.mandante_id from usuario_mandante_rol umr2
                                  where umr2.usuario_id = u.id and umr2.activo = 1
                           )) as mandante_codigo'),
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

    /**
     * Los mandantes cuyos usuarios se pueden ver y tocar desde esta pantalla.
     * `null` significa «sin restricción», y a partir de aquí eso casi nunca pasa.
     *
     * Antes el ADMIN_GLOBAL devolvía siempre null y por eso veía los usuarios de
     * las cuatro empresas en una sola tabla, sin ninguna señal de a quién
     * pertenecía cada uno. Era el riesgo que el dueño describió: borrar por error
     * a alguien de otro cliente. Ahora manda el CLIENTE ACTIVO (decisión D1): el
     * admin global sigue alcanzándolos todos, pero trabaja dentro de uno y ve uno.
     *
     * @return list<int>|null
     */
    private function mandantesPermitidos(): ?array
    {
        if (app()->bound('tenancy.mandante_activo')) {
            $mandante = app('tenancy.mandante_activo');

            return [(int) (is_object($mandante) ? $mandante->id : $mandante)];
        }

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

    /**
     * El cliente en el que transcurre esta acción.
     *
     * El binding lo publica el middleware, así que en una petición HTTP normal
     * está. Pero no puede ser la única fuente: en una acción de Livewire probada
     * en aislamiento no hay middleware, y un admin que administra un solo cliente
     * no tiene ninguna ambigüedad que resolver. Se cae a él antes que a nada.
     */
    private function clienteDondeSeEstaTrabajando(): ?int
    {
        if (app()->bound('tenancy.mandante_activo')) {
            $mandante = app('tenancy.mandante_activo');

            return (int) (is_object($mandante) ? $mandante->id : $mandante);
        }

        $propios = $this->mandantesPermitidos();

        return is_array($propios) && count($propios) === 1 ? $propios[0] : null;
    }

    /**
     * Deja constancia de a qué cliente pertenece un usuario recién creado.
     *
     * Se usa el pivot de mandante y no una columna en `users` a propósito: es el
     * mecanismo que ya existe (F38) y el que consultan el resto de pantallas.
     */
    private function ligarAlClienteActivo(int $usuarioId): void
    {
        $mandanteId = $this->clienteDondeSeEstaTrabajando();

        if ($mandanteId === null) {
            return;
        }

        $rolId = (int) DB::table('roles')->where('codigo', 'GESTOR')->value('id');

        if ($rolId <= 0) {
            return;
        }

        DB::table('usuario_mandante_rol')->insertOrIgnore([
            'usuario_id' => $usuarioId,
            'mandante_id' => $mandanteId,
            'rol_id' => $rolId,
            'activo' => true,
        ]);
    }

    /**
     * Un ADMIN_MANDANTE solo puede tocar usuarios de su propio alcance, y nunca
     * a alguien con rol global.
     *
     * Hasta ahora el scoping vivia solo en render() — es decir, en la lectura —
     * mientras guardarUsuario() reescribia correo y contrasenia de cualquier id.
     * Con editandoUsuarioId como propiedad publica de Livewire, ese id lo elige
     * el cliente, asi que la comprobacion tiene que estar en la accion.
     */
    private function guardContraUsuarioAjeno(int $usuarioId): void
    {
        $mandantes = $this->mandantesPermitidos();

        if ($mandantes === null) {
            return; // ADMIN_GLOBAL.
        }

        $objetivoEsGlobal = DB::table('usuario_global_rol')
            ->where('usuario_id', $usuarioId)
            ->exists();

        if ($objetivoEsGlobal) {
            abort(403, 'No puedes gestionar a un usuario con rol global.');
        }

        $proyectos = $this->proyectosDeMandantes($mandantes);

        $enAlcance = DB::table('usuario_proyecto_rol')
            ->where('usuario_id', $usuarioId)
            ->whereIn('proyecto_id', $proyectos)
            ->exists()
            || DB::table('usuario_mandante_rol')
                ->where('usuario_id', $usuarioId)
                ->whereIn('mandante_id', $mandantes)
                ->exists();

        if (! $enAlcance) {
            abort(403, 'Ese usuario no pertenece a tu alcance.');
        }
    }

    /**
     * Variante para asignaciones: una cuenta recién creada desde el panel aún no
     * pertenece a nadie y debe poder asignarse; lo que se corta es traerse a un
     * usuario que ya vive en otro mandante.
     */
    private function guardContraUsuarioDeOtroMandante(int $usuarioId): void
    {
        if ($this->mandantesPermitidos() === null) {
            return;
        }

        $tienePivots = DB::table('usuario_proyecto_rol')->where('usuario_id', $usuarioId)->exists()
            || DB::table('usuario_mandante_rol')->where('usuario_id', $usuarioId)->exists();

        if ($tienePivots) {
            $this->guardContraUsuarioAjeno($usuarioId);

            return;
        }

        $objetivoEsGlobal = DB::table('usuario_global_rol')->where('usuario_id', $usuarioId)->exists();

        if ($objetivoEsGlobal) {
            abort(403, 'No puedes gestionar a un usuario con rol global.');
        }
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
