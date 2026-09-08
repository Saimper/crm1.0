<?php

declare(strict_types=1);

namespace App\Modules\Usuarios\Infrastructure\Http\Livewire;

use App\Models\User;
use App\Modules\Usuarios\Application\RolesCustom\UseCases\AsignarRolCustomAUsuario;
use App\Modules\Usuarios\Application\RolesCustom\UseCases\RevocarRolCustomDeUsuario;
use App\Support\Livewire\AutorizaEnProyectoActivo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Gestión de roles por proyecto para SUPERVISOR (y ADMIN_GLOBAL via Gate::before).
 * Scoped al proyecto activo. Maneja roles base (SUPERVISOR/GESTOR/AUDITOR) y,
 * desde Fase 33, también roles custom del proyecto.
 *
 * Protecciones:
 *   - `usuarios.gestionar` exigido en cada método que escribe, no sólo en la
 *     ruta: cada acción de Livewire es un POST aparte que no vuelve a pasar por
 *     el middleware, y esta pantalla reparte roles.
 *   - No mostrar ni modificar usuarios con rol ADMIN_GLOBAL.
 *   - No permitir auto-revocarse.
 *   - Validar que el usuario existe antes de asignar.
 *   - Roles custom no admiten cartera-scoping (se ignora `carterasSeleccionadas`).
 */
final class GestionUsuariosProyecto extends Component
{
    use AutorizaEnProyectoActivo;

    public bool $formAsignarVisible = false;

    public string $buscarEmail = '';

    /**
     * `#[Locked]` porque este id lo fija el servidor en `buscarUsuario()`, que es
     * donde viven las dos negativas que importan: no se gestiona a un
     * ADMIN_GLOBAL y no se trae por correo a alguien de otro mandante. Sin el
     * candado, un `$wire.set('usuarioBuscadoId', N)` desde la consola llegaba a
     * `asignar()` sin haber pasado por ninguna de las dos, y el pivot que se
     * creaba es el que el SSO usa como prueba de identidad.
     */
    #[Locked]
    public ?int $usuarioBuscadoId = null;

    public string $usuarioBuscadoNombre = '';

    /**
     * Valor del select de rol. Formato:
     *   - "base:{rol_id}"     → rol del sistema (SUPERVISOR/GESTOR/AUDITOR)
     *   - "custom:{rol_custom_id}" → rol custom del proyecto.
     */
    public string $rolAsignarValor = '';

    /** @var list<int> IDs de carteras que restringen el rol (vacío = todo el proyecto). */
    public array $carterasSeleccionadas = [];

    public function abrirFormAsignar(): void
    {
        $this->formAsignarVisible = true;
        $this->buscarEmail = '';
        $this->usuarioBuscadoId = null;
        $this->usuarioBuscadoNombre = '';
        $this->rolAsignarValor = '';
        $this->carterasSeleccionadas = [];
        $this->resetErrorBag();
    }

    public function cerrarFormAsignar(): void
    {
        $this->formAsignarVisible = false;
        $this->usuarioBuscadoId = null;
        $this->usuarioBuscadoNombre = '';
        $this->rolAsignarValor = '';
        $this->carterasSeleccionadas = [];
        $this->resetErrorBag();
    }

    public function buscarUsuario(): void
    {
        // Resolver un correo a un nombre y un id es el paso previo a repartir un
        // rol, y contesta si esa cuenta existe en el CRM. Mismo permiso que
        // asignar: quien no puede asignar no tiene por qué enumerar.
        $this->autorizarEn('usuarios.gestionar');

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

        if ($this->perteneceAOtroMandante((int) $user->id)) {
            // Traer por correo a un usuario de otro mandante creaba aquí el
            // pivot que el SSO usa como prueba de identidad: bastaba con
            // conocer el correo para que el token del propio tenant pasara a
            // resolver a esa persona. Vincular a alguien entre mandantes es
            // una decisión de administración global, no de un supervisor.
            $this->addError('buscarEmail', 'Ese usuario pertenece a otro mandante. Pídelo a un administrador global.');
            $this->usuarioBuscadoId = null;
            $this->usuarioBuscadoNombre = '';

            return;
        }

        $this->usuarioBuscadoId = (int) $user->id;
        $this->usuarioBuscadoNombre = (string) $user->name;
    }

    public function asignar(AsignarRolCustomAUsuario $asignarCustom): void
    {
        // `usuarios.gestionar` es el permiso de la ruta y el que nombra esta
        // función en el reparto: lo tienen SUPERVISOR, ADMIN_MANDANTE y
        // ADMIN_GLOBAL. El AUDITOR se queda en `usuarios.ver` y el GESTOR no
        // tiene ninguno del grupo.
        $this->autorizarEn('usuarios.gestionar');

        $this->validate([
            'usuarioBuscadoId' => ['required', 'integer', 'exists:users,id'],
            'rolAsignarValor' => ['required', 'string', 'regex:/^(base|custom):\d+$/'],
        ], [], [
            'usuarioBuscadoId' => 'usuario',
            'rolAsignarValor' => 'rol',
        ]);

        [$tipo, $idStr] = explode(':', $this->rolAsignarValor, 2);
        $proyectoId = $this->proyectoActivoId();
        $usuarioId = (int) $this->usuarioBuscadoId;
        $rolId = (int) $idStr;

        if ($tipo === 'base') {
            $rol = DB::table('roles')->where('id', $rolId)->first();
            if ($rol === null || ! in_array($rol->codigo, ['SUPERVISOR', 'GESTOR', 'AUDITOR'], true)) {
                $this->addError('rolAsignarValor', 'Rol no válido para asignación por proyecto.');

                return;
            }

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
                        'usuario_id' => $usuarioId,
                        'proyecto_id' => $proyectoId,
                        'rol_id' => $rolId,
                        'cartera_id' => $cid,
                    ],
                    $carterasValidas,
                );
                DB::table('usuario_proyecto_rol_cartera')->insert($filas);
            }
        } else {
            // Un rol custom es del proyecto que lo define. El permiso es por
            // proyecto, así que sin esta comprobación un supervisor arrastraría
            // el suyo para colgarle a alguien el rol de otro mandante.
            $this->exigirDelProyecto('roles_custom', $rolId);

            try {
                $asignarCustom->execute($rolId, $usuarioId, $proyectoId);
            } catch (\Throwable $e) {
                $this->addError('rolAsignarValor', $e->getMessage());

                return;
            }
        }

        $this->cerrarFormAsignar();
        session()->flash('gestion-usuarios-ok', 'Rol asignado.');
    }

    public function quitar(int $usuarioId, int $rolId): void
    {
        // Los dos argumentos llegan del cliente. El DELETE ya filtra por el
        // proyecto activo, así que la fuga posible no es de fila sino de
        // permiso: revocar roles es gestionar usuarios, no verlos.
        $this->autorizarEn('usuarios.gestionar');

        if (! $this->validarPuedeQuitar($usuarioId)) {
            return;
        }

        DB::table('usuario_proyecto_rol')
            ->where('usuario_id', $usuarioId)
            ->where('proyecto_id', $this->proyectoActivoId())
            ->where('rol_id', $rolId)
            ->delete();

        session()->flash('gestion-usuarios-ok', 'Rol removido.');
    }

    public function quitarCustom(int $usuarioId, int $rolCustomId, RevocarRolCustomDeUsuario $revocar): void
    {
        $this->autorizarEn('usuarios.gestionar');
        $this->exigirDelProyecto('roles_custom', $rolCustomId);

        if (! $this->validarPuedeQuitar($usuarioId)) {
            return;
        }

        $revocar->execute($rolCustomId, $usuarioId, $this->proyectoActivoId());
        session()->flash('gestion-usuarios-ok', 'Rol custom removido.');
    }

    private function validarPuedeQuitar(int $usuarioId): bool
    {
        if ($usuarioId === (int) auth()->id()) {
            session()->flash('gestion-usuarios-error', 'No puedes quitarte a ti mismo un rol en este proyecto.');

            return false;
        }

        /** @var User|null $target */
        $target = User::query()->find($usuarioId);
        if ($target === null || $target->esAdminGlobal()) {
            session()->flash('gestion-usuarios-error', 'No se puede modificar este usuario desde aquí.');

            return false;
        }

        return true;
    }

    public function render(): View
    {
        $proyectoId = $this->proyectoActivoId();

        $asignacionesBase = DB::table('usuario_proyecto_rol as upr')
            ->join('users as u', 'u.id', '=', 'upr.usuario_id')
            ->join('roles as r', 'r.id', '=', 'upr.rol_id')
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
                DB::raw("'base' as tipo_rol"),
                DB::raw('max(case when rg.codigo = "ADMIN_GLOBAL" then 1 else 0 end) as es_admin_global'),
            ])
            ->groupBy(
                'upr.usuario_id', 'upr.rol_id', 'upr.equipo_id',
                'u.name', 'u.email', 'u.activo',
                'r.codigo', 'r.nombre',
            )
            ->orderBy('u.name')
            ->get()
            ->filter(fn (object $a): bool => ! (bool) $a->es_admin_global);

        $asignacionesCustom = DB::table('usuario_proyecto_rol_custom as uprc')
            ->join('users as u', 'u.id', '=', 'uprc.usuario_id')
            ->join('roles_custom as rc', 'rc.id', '=', 'uprc.rol_custom_id')
            ->leftJoin('usuario_global_rol as ugr', function ($j): void {
                $j->on('ugr.usuario_id', '=', 'u.id');
            })
            ->leftJoin('roles as rg', function ($j): void {
                $j->on('rg.id', '=', 'ugr.rol_id')->where('rg.codigo', 'ADMIN_GLOBAL');
            })
            ->where('uprc.proyecto_id', $proyectoId)
            ->where('uprc.activo', true)
            ->whereNull('rc.eliminada_en')
            ->select([
                'uprc.usuario_id',
                DB::raw('uprc.rol_custom_id as rol_id'),
                DB::raw('null as equipo_id'),
                'u.name', 'u.email', 'u.activo as usuario_activo',
                'rc.codigo as rol_codigo', 'rc.nombre as rol_nombre',
                DB::raw("'custom' as tipo_rol"),
                DB::raw('max(case when rg.codigo = "ADMIN_GLOBAL" then 1 else 0 end) as es_admin_global'),
            ])
            ->groupBy(
                'uprc.usuario_id', 'uprc.rol_custom_id',
                'u.name', 'u.email', 'u.activo',
                'rc.codigo', 'rc.nombre',
            )
            ->orderBy('u.name')
            ->get()
            ->filter(fn (object $a): bool => ! (bool) $a->es_admin_global);

        $asignaciones = $asignacionesBase
            ->concat($asignacionesCustom)
            ->groupBy('usuario_id');

        $rolesAsignablesBase = DB::table('roles')
            ->where('activo', true)
            ->whereIn('codigo', ['SUPERVISOR', 'GESTOR', 'AUDITOR'])
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre']);

        $rolesAsignablesCustom = DB::table('roles_custom')
            ->where('proyecto_id', $proyectoId)
            ->where('activo', true)
            ->whereNull('eliminada_en')
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre']);

        $carterasDelProyecto = DB::table('carteras')
            ->where('proyecto_id', $proyectoId)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre']);

        $restricciones = DB::table('usuario_proyecto_rol_cartera as rc')
            ->join('carteras as c', 'c.id', '=', 'rc.cartera_id')
            ->where('rc.proyecto_id', $proyectoId)
            ->select(['rc.usuario_id', 'rc.rol_id', 'c.nombre as cartera_nombre', 'c.id as cartera_id'])
            ->get()
            ->groupBy(fn ($r) => $r->usuario_id.'-'.$r->rol_id);

        return view('usuarios::admin.gestion-usuarios-proyecto', [
            'asignaciones' => $asignaciones,
            'rolesAsignablesBase' => $rolesAsignablesBase,
            'rolesAsignablesCustom' => $rolesAsignablesCustom,
            'carterasDelProyecto' => $carterasDelProyecto,
            'restricciones' => $restricciones,
            'usuarioActualId' => (int) auth()->id(),
        ]);
    }

    /**
     * El usuario esta ligado a un mandante DISTINTO del proyecto activo.
     *
     * Se bloquea solo ese caso, no "que no sea mio": una cuenta recien creada
     * desde el panel todavia no pertenece a nadie, y asignarla es justamente el
     * alta normal. Lo que no puede pasar es traerse por correo a alguien de
     * otro mandante, porque el pivot que se crearia aqui es la prueba de
     * identidad que consulta el SSO.
     */
    private function perteneceAOtroMandante(int $usuarioId): bool
    {
        $mandanteId = (int) DB::table('proyectos')
            ->where('id', $this->proyectoActivoId())
            ->value('mandante_id');

        if ($mandanteId <= 0) {
            return true;
        }

        $origen = DB::table('users')->where('id', $usuarioId)->value('mandante_origen_id');

        if ($origen !== null && (int) $origen !== $mandanteId) {
            return true;
        }

        $enOtroMandante = DB::table('usuario_mandante_rol')
            ->where('usuario_id', $usuarioId)
            ->where('mandante_id', '!=', $mandanteId)
            ->where('activo', true)
            ->exists();

        if ($enOtroMandante) {
            return true;
        }

        return DB::table('usuario_proyecto_rol as upr')
            ->join('proyectos as p', 'p.id', '=', 'upr.proyecto_id')
            ->where('upr.usuario_id', $usuarioId)
            ->where('p.mandante_id', '!=', $mandanteId)
            ->where('upr.activo', true)
            ->exists();
    }
}
