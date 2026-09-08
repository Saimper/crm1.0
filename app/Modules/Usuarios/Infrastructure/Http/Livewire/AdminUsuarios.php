<?php

declare(strict_types=1);

namespace App\Modules\Usuarios\Infrastructure\Http\Livewire;

use App\Models\User;
use App\Modules\Auditoria\Domain\Contracts\RegistroDeAccionesAdministrativas;
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
 *
 * Todas dejan rastro en `auditorias`, y ninguna lo dejaba antes: las cuentas se
 * escribían por un modelo que nadie observaba y los roles por pivotes que
 * ningún observer puede ver. Dar de alta a alguien y darle acceso a los datos de
 * un cliente son las dos acciones más sensibles de la aplicación, y ocurrían sin
 * testigos. El rastro va en la MISMA transacción que la escritura: un acceso
 * concedido cuyo registro se perdió es peor que no tener registro, porque nadie
 * sabe que falta.
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
            $this->crearUsuario();
        } else {
            $this->actualizarUsuario($this->editandoUsuarioId);
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

        DB::transaction(function () use ($usuarioId, $rolAdminGlobalId): void {
            DB::table('usuario_global_rol')->insert([
                'usuario_id' => $usuarioId,
                'rol_id' => $rolAdminGlobalId,
            ]);

            // Es la única puerta que abre todos los clientes a la vez, y se abría
            // sin testigos. El evento se atribuye al cliente del que sale la
            // persona: es su empleado el que acaba de recibir la llave de todas
            // las demás puertas, y su administrador tiene que poder verlo.
            $this->bitacora()->alta(
                'usuario_global_rol',
                $usuarioId,
                ['usuario_id' => $usuarioId, 'rol_codigo' => 'ADMIN_GLOBAL'],
                null,
                $this->clienteDeLaCuenta($usuarioId),
            );
        });

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

        DB::transaction(function () use ($usuarioId, $rolAdminGlobalId): void {
            $revocadas = DB::table('usuario_global_rol')
                ->where('usuario_id', $usuarioId)
                ->where('rol_id', $rolAdminGlobalId)
                ->delete();

            if ($revocadas === 0) {
                return;
            }

            $this->bitacora()->baja(
                'usuario_global_rol',
                $usuarioId,
                ['usuario_id' => $usuarioId, 'rol_codigo' => 'ADMIN_GLOBAL'],
                null,
                $this->clienteDeLaCuenta($usuarioId),
            );
        });

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

        $usuarioId = (int) $this->usuarioAsignandoId;
        $proyectoId = (int) $this->asignarProyectoId;
        $rolId = (int) $this->asignarRolId;

        DB::transaction(function () use ($usuarioId, $proyectoId, $rolId): void {
            // Un usuario puede tener múltiples roles en el mismo proyecto (PK compuesta usuario+proyecto+rol).
            // Upsert para reactivar si ya existía en inactivo.
            DB::table('usuario_proyecto_rol')->upsert(
                [[
                    'usuario_id' => $usuarioId,
                    'proyecto_id' => $proyectoId,
                    'rol_id' => $rolId,
                    'equipo_id' => null,
                    'activo' => true,
                ]],
                ['usuario_id', 'proyecto_id', 'rol_id'],
                ['equipo_id', 'activo'],
            );

            // Conceder acceso a los datos de un cliente es el evento más
            // sensible del sistema. La pivote no tiene modelo que observar, así
            // que el rastro se escribe aquí y en la misma transacción.
            $this->bitacora()->alta(
                'usuario_proyecto_rol',
                $usuarioId,
                $this->retratoDeLaAsignacion($usuarioId, $proyectoId, $rolId),
                $proyectoId,
            );
        });

        $this->cerrarFormAsignacion();
        session()->flash('admin-usuarios-ok', 'Asignación guardada.');
    }

    public function quitarAsignacion(int $usuarioId, int $proyectoId, int $rolId): void
    {
        $this->guardContraUsuarioDeOtroMandante($usuarioId);
        $this->guardContraProyectoAjeno($proyectoId);

        DB::transaction(function () use ($usuarioId, $proyectoId, $rolId): void {
            // El retrato se toma ANTES de borrar: después la fila ya no está y
            // la auditoría sería el único sitio donde quedó, vacío.
            $retrato = $this->retratoDeLaAsignacion($usuarioId, $proyectoId, $rolId);

            $quitadas = DB::table('usuario_proyecto_rol')
                ->where('usuario_id', $usuarioId)
                ->where('proyecto_id', $proyectoId)
                ->where('rol_id', $rolId)
                ->delete();

            if ($quitadas === 0) {
                return;
            }

            $this->bitacora()->baja('usuario_proyecto_rol', $usuarioId, $retrato, $proyectoId);
        });

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

    /**
     * Alta de cuenta: tres tablas (`users`, el pivot del cliente y `auditorias`)
     * y por tanto una transacción (§11).
     *
     * Lo que se audita va enumerado a mano. No es lo mismo que dejar que un
     * observer fotografíe la fila y borre después lo sensible: aquí el hash de
     * la contraseña no tiene forma de llegar al registro, ni hoy ni el día que
     * `users` gane otra columna secreta.
     */
    private function crearUsuario(): void
    {
        $datos = [
            'name' => (string) $this->formUsuario['name'],
            'email' => strtolower((string) $this->formUsuario['email']),
            'activo' => (bool) ($this->formUsuario['activo'] ?? true),
        ];

        DB::transaction(function () use ($datos): void {
            $nuevo = User::query()->create([
                ...$datos,
                'password' => Hash::make((string) $this->formUsuario['password']),
            ]);

            // Nace ligado al cliente en el que se está trabajando. Sin esto el
            // usuario quedaba huérfano y el propio filtro de la pantalla lo
            // escondía de quien acababa de crearlo.
            $this->ligarAlClienteActivo((int) $nuevo->id);

            $this->bitacora()->alta(
                'users',
                (int) $nuevo->id,
                $datos,
                null,
                $this->clienteDondeSeEstaTrabajando(),
            );
        });
    }

    /**
     * Edición de cuenta. El correo es la identidad con la que se entra —también
     * por SSO, que resuelve al usuario por email—, así que cambiarlo es cambiar
     * quién puede entrar en esa cuenta: sin rastro, eso no se puede reconstruir
     * después.
     */
    private function actualizarUsuario(int $usuarioId): void
    {
        $u = User::query()->findOrFail($usuarioId);

        $antes = [
            'name' => (string) $u->name,
            'email' => (string) $u->email,
            'activo' => (bool) ($u->activo ?? true),
        ];
        $despues = [
            'name' => (string) $this->formUsuario['name'],
            'email' => strtolower((string) $this->formUsuario['email']),
            'activo' => (bool) ($this->formUsuario['activo'] ?? true),
        ];
        $reemplazaContrasena = ! empty($this->formUsuario['password']);

        DB::transaction(function () use ($u, $antes, $despues, $reemplazaContrasena): void {
            $u->name = $despues['name'];
            $u->email = $despues['email'];
            $u->activo = $despues['activo'];
            if ($reemplazaContrasena) {
                $u->password = Hash::make((string) $this->formUsuario['password']);
            }
            $u->save();

            $this->bitacora()->cambio(
                'users',
                (int) $u->id,
                $this->diferencias($antes, $despues, $reemplazaContrasena),
                null,
                $this->clienteDeLaCuenta((int) $u->id),
            );
        });
    }

    /**
     * Qué cambió, en el mismo formato campo → antes/después que el resto de la
     * auditoría (así el detalle de la pantalla lo pinta sin un caso aparte).
     *
     * De la contraseña se registra el HECHO y jamás el valor: que un
     * administrador le cambie la contraseña a otro es exactamente lo que hay que
     * poder reconstruir, y el hash no aporta nada a esa reconstrucción salvo
     * material para atacarlo sin prisa y sin conexión.
     *
     * @param  array<string, mixed>  $antes
     * @param  array<string, mixed>  $despues
     * @return array<string, array{antes: mixed, despues: mixed}>
     */
    private function diferencias(array $antes, array $despues, bool $reemplazaContrasena): array
    {
        $cambios = [];

        foreach ($despues as $campo => $valor) {
            if (($antes[$campo] ?? null) !== $valor) {
                $cambios[$campo] = ['antes' => $antes[$campo] ?? null, 'despues' => $valor];
            }
        }

        if ($reemplazaContrasena) {
            $cambios['password'] = ['antes' => null, 'despues' => 'reemplazada por un administrador'];
        }

        return $cambios;
    }

    /** Quien escribe el rastro de esta pantalla. Contrato del módulo Auditoría (§3). */
    private function bitacora(): RegistroDeAccionesAdministrativas
    {
        return app(RegistroDeAccionesAdministrativas::class);
    }

    /**
     * El cliente al que pertenece una cuenta, para que sus eventos —que no
     * cuelgan de ningún proyecto— tengan dueño y su administrador los vea.
     *
     * Primero el origen de la cuenta (`mandante_origen_id`, lo que dejó escrito
     * quien la provisionó) y sólo después el cliente en el que se está
     * trabajando: un ADMIN_GLOBAL puede editar la cuenta de cualquiera desde
     * fuera de todo contexto, y ahí el dueño del evento es el cliente de la
     * cuenta, no el de la pantalla.
     */
    private function clienteDeLaCuenta(int $usuarioId): ?int
    {
        $origen = DB::table('users')->where('id', $usuarioId)->value('mandante_origen_id');

        if ($origen !== null) {
            return (int) $origen;
        }

        return $this->clienteDondeSeEstaTrabajando();
    }

    /**
     * Lo que se guarda de una asignación de rol.
     *
     * Lleva el código del rol además de su id porque quien lea esto dentro de
     * un año necesita ver «SUPERVISOR», no el número de una fila de `roles` que
     * para entonces puede significar otra cosa.
     *
     * @return array<string, mixed>
     */
    private function retratoDeLaAsignacion(int $usuarioId, int $proyectoId, int $rolId): array
    {
        return [
            'usuario_id' => $usuarioId,
            'proyecto_id' => $proyectoId,
            'proyecto_codigo' => DB::table('proyectos')->where('id', $proyectoId)->value('codigo'),
            'rol_id' => $rolId,
            'rol_codigo' => DB::table('roles')->where('id', $rolId)->value('codigo'),
        ];
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
