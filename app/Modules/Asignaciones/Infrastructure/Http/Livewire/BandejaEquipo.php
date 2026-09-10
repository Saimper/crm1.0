<?php

declare(strict_types=1);

namespace App\Modules\Asignaciones\Infrastructure\Http\Livewire;

use App\Models\User;
use App\Modules\Asignaciones\Application\UseCases\ReasignarAsignacionAUsuario;
use App\Modules\Asignaciones\Domain\Exceptions\TransicionAsignacionInvalida;
use App\Support\Database\CarterasOperativas;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Bandeja de supervisión: asignaciones de los miembros activos de un equipo.
 * Permiso requerido: asignaciones.ver_equipo.
 *
 * Filtros:
 *   - equipoId (obligatorio para poblar la lista).
 *   - miembroId (opcional) — limita a un solo usuario.
 *   - estado ('todos' | pendiente | en_trabajo | cerrada).
 *   - búsqueda libre (identificación/nombre/razón).
 */
final class BandejaEquipo extends Component
{
    use WithPagination;

    #[Url(as: 'equipo')]
    public ?int $equipoId = null;

    #[Url(as: 'miembro')]
    public ?int $miembroId = null;

    #[Url(as: 'estado')]
    public string $estadoFiltro = 'pendiente';

    #[Url(as: 'q', except: '')]
    public string $busqueda = '';

    public ?string $mensajeExito = null;

    public function updating(): void
    {
        $this->resetPage();
    }

    public function updatedEquipoId(): void
    {
        $this->miembroId = null;
        $this->resetPage();
    }

    /**
     * Ajusta la prioridad de una asignación. Solo SUPERVISOR + ADMIN_GLOBAL
     * (mismo permiso que ver_equipo + reasignar para no abrir un permiso nuevo).
     * Rango 0..9.
     */
    public function cambiarPrioridad(int $asignacionId, int $nuevaPrioridad): void
    {
        $proyectoId = (int) app('tenancy.proyecto_activo')->id;

        if (auth()->user()?->tienePermiso('asignaciones.reasignar', $proyectoId) !== true) {
            abort(403, 'No tienes permiso para cambiar prioridades en este proyecto.');
        }

        $nuevaPrioridad = max(0, min(9, $nuevaPrioridad));

        DB::table('asignaciones')
            ->whereIn('caso_id', CarterasOperativas::casos(DB::connection(), $proyectoId)->select('c.id'))
            ->where('id', $asignacionId)
            ->where('proyecto_id', $proyectoId)
            ->update(['prioridad' => $nuevaPrioridad]);
    }

    /**
     * El supervisor le pasa una cuenta de un asesor a otro.
     *
     * Mismo permiso que el reparto por lotes: quien puede mover mil puede mover
     * una. Las reglas —qué estados se mueven, que el destino opere en el
     * proyecto, que una cerrada se reabra al pasarla— viven en el UseCase, no
     * aquí (§13.4).
     */
    public function reasignar(int $asignacionId, ?int $nuevoUsuarioId, ReasignarAsignacionAUsuario $reasignar): void
    {
        if ($nuevoUsuarioId === null || $nuevoUsuarioId <= 0) {
            return;
        }

        $proyectoId = (int) app('tenancy.proyecto_activo')->id;

        abort_unless(
            auth()->user()?->tienePermiso('asignaciones.reasignar', $proyectoId) === true,
            403,
            'No tienes permiso para reasignar cuentas en este proyecto.',
        );

        // Se lee ANTES: si estaba cerrada, el UseCase la reabre y después ya no
        // hay forma de saber que la cuenta acaba de volver a circulación, que es
        // justo lo que hay que contarle al supervisor.
        $estabaCerrada = DB::table('asignaciones')
            ->whereIn('caso_id', CarterasOperativas::casos(DB::connection(), $proyectoId)->select('c.id'))
            ->where('id', $asignacionId)
            ->where('proyecto_id', $proyectoId)
            ->value('estado') === 'cerrada';

        try {
            $reasignar->execute($proyectoId, $asignacionId, $nuevoUsuarioId);
            $nombre = (string) DB::table('users')->where('id', $nuevoUsuarioId)->value('name');
            $this->mensajeExito = __(
                $estabaCerrada ? 'asignaciones.reopen_done' : 'asignaciones.reassign_done',
                ['usuario' => $nombre],
            );
        } catch (TransicionAsignacionInvalida $e) {
            $this->addError('reasignacion', $e->getMessage());
        }
    }

    private function usuarioAutenticado(): User
    {
        $usuario = auth()->user();
        abort_unless($usuario instanceof User, 401);

        return $usuario;
    }

    /**
     * A quién puede pasarse una cuenta: cualquiera que opere en el proyecto, no
     * sólo el equipo que se está mirando —mover fuera del equipo es justamente
     * uno de los motivos para reasignar—.
     *
     * @return Collection<int, \stdClass>
     */
    private function destinatarios(int $proyectoId): Collection
    {
        return DB::table('users as u')
            ->where('u.activo', true)
            ->where(fn ($w) => $w
                ->whereExists(fn ($q) => $q->from('usuario_proyecto_rol as upr')
                    ->whereColumn('upr.usuario_id', 'u.id')
                    ->where('upr.proyecto_id', $proyectoId)
                    ->where('upr.activo', true))
                ->orWhereExists(fn ($q) => $q->from('usuario_proyecto_rol_custom as uprc')
                    ->whereColumn('uprc.usuario_id', 'u.id')
                    ->where('uprc.proyecto_id', $proyectoId)
                    ->where('uprc.activo', true)))
            ->orderBy('u.name')
            ->get(['u.id', 'u.name']);
    }

    public function render(): View
    {
        abort_unless(auth()->user()?->tienePermiso('asignaciones.ver_equipo') === true, 403);

        $proyectoActivo = app('tenancy.proyecto_activo');
        $proyectoId = (int) $proyectoActivo->id;

        $equipos = DB::table('equipos')
            ->where('proyecto_id', $proyectoId)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre']);

        $miembros = collect();
        if ($this->equipoId !== null) {
            $miembros = DB::table('equipo_usuario as eu')
                ->join('users as u', 'u.id', '=', 'eu.usuario_id')
                ->where('eu.proyecto_id', $proyectoId)
                ->where('eu.equipo_id', $this->equipoId)
                ->where('eu.activo', true)
                ->select(['u.id', 'u.name'])
                ->orderBy('u.name')
                ->get();
        }

        $miembroIds = $miembros->pluck('id')->map(fn ($v) => (int) $v)->all();

        // Validar que miembroId (si se especificó) pertenezca al equipo.
        if ($this->miembroId !== null && ! in_array($this->miembroId, $miembroIds, true)) {
            $this->miembroId = null;
        }

        $asignaciones = collect();
        $conteoPorEstado = collect();
        $conteoPorMiembro = collect();

        if ($this->equipoId !== null && $miembroIds !== []) {
            $usuariosQuery = $this->miembroId !== null ? [$this->miembroId] : $miembroIds;

            $query = DB::table('asignaciones as a')
                ->join('casos as c', 'c.id', '=', 'a.caso_id')
                ->join('personas as pe', 'pe.id', '=', 'c.persona_id')
                ->join('carteras as ca', 'ca.id', '=', 'c.cartera_id')
                ->join('estados_caso as ec', 'ec.id', '=', 'c.estado_caso_id')
                ->join('users as gu', 'gu.id', '=', 'a.usuario_id')
                ->leftJoin('resultados as ru', 'ru.id', '=', 'c.resultado_ultima_gestion_id')
                ->where('a.proyecto_id', $proyectoId)
                ->whereIn('a.usuario_id', $usuariosQuery)
                ->whereNull('c.eliminada_en')
                ->where(fn ($q) => CarterasOperativas::filtrar($q));

            if ($this->estadoFiltro !== 'todos') {
                $query->where('a.estado', $this->estadoFiltro);
            }

            $texto = trim($this->busqueda);
            if ($texto !== '') {
                $like = "%{$texto}%";
                $query->where(function ($w) use ($like): void {
                    $w->where('pe.identificacion', 'like', $like)
                        ->orWhere('pe.nombres', 'like', $like)
                        ->orWhere('pe.apellidos', 'like', $like)
                        ->orWhere('pe.razon_social', 'like', $like);
                });
            }

            $asignaciones = $query
                ->select([
                    'a.id', 'a.public_id as asignacion_public_id', 'a.estado',
                    'a.prioridad', 'a.fecha_asignacion',
                    'c.public_id as caso_public_id', 'c.tipo_caso',
                    'c.fecha_ultima_gestion', 'c.tiene_compromiso_vigente',
                    'pe.public_id as persona_public_id',
                    'pe.identificacion', 'pe.tipo_persona',
                    'pe.nombres', 'pe.apellidos', 'pe.razon_social',
                    'ec.nombre as estado_caso_nombre',
                    'ca.nombre as cartera_nombre',
                    'ru.nombre as resultado_ultimo',
                    'gu.id as gestor_id', 'gu.name as gestor_nombre',
                ])
                ->orderBy('gu.name')
                ->orderByDesc('a.prioridad')
                ->orderByDesc('c.fecha_ultima_gestion')
                ->paginate(25);

            $conteoPorEstado = DB::table('asignaciones')
                ->whereIn('caso_id', CarterasOperativas::casos(DB::connection(), $proyectoId)->select('c.id'))
                ->where('proyecto_id', $proyectoId)
                ->whereIn('usuario_id', $miembroIds)
                ->selectRaw('estado, count(*) as total')
                ->groupBy('estado')
                ->pluck('total', 'estado');

            $conteoPorMiembro = DB::table('asignaciones as a')
                ->join('users as u', 'u.id', '=', 'a.usuario_id')
                ->whereIn('a.caso_id', CarterasOperativas::casos(DB::connection(), $proyectoId)->select('c.id'))
                ->where('a.proyecto_id', $proyectoId)
                ->whereIn('a.usuario_id', $miembroIds)
                ->selectRaw('u.id, u.name, a.estado, count(*) as total')
                ->groupBy('u.id', 'u.name', 'a.estado')
                ->get();
        }

        return view('asignaciones::livewire.bandeja-equipo', [
            'equipos' => $equipos,
            'miembros' => $miembros,
            'asignaciones' => $asignaciones,
            'conteoPorEstado' => $conteoPorEstado,
            'conteoPorMiembro' => $conteoPorMiembro,
            'proyectoActivo' => $proyectoActivo,
            'destinatarios' => $this->destinatarios($proyectoId),
            'puedeReasignar' => $this->usuarioAutenticado()->tienePermiso('asignaciones.reasignar', $proyectoId),
        ]);
    }
}
