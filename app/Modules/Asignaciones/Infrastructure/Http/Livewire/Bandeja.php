<?php

declare(strict_types=1);

namespace App\Modules\Asignaciones\Infrastructure\Http\Livewire;

use App\Models\User;
use App\Modules\Asignaciones\Application\UseCases\AutoasignarCaso;
use App\Modules\Asignaciones\Application\UseCases\CerrarAsignacion;
use App\Modules\Asignaciones\Domain\Exceptions\AutoasignacionNoPermitida;
use App\Modules\Asignaciones\Domain\Exceptions\TransicionAsignacionInvalida;
use App\Support\Database\CarterasOperativas;
use DateTimeImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

final class Bandeja extends Component
{
    use WithPagination;

    /** Pestaña de las cuentas que no son de nadie; no es un estado de asignación. */
    private const POOL = 'sin_duenio';

    #[Url(as: 'estado')]
    public string $estadoFiltro = 'pendiente';

    #[Url(as: 'q', except: '')]
    public string $busqueda = '';

    public ?string $mensajeExito = null;

    /**
     * Aterrizaje del screen-pop: el handshake del wrapper trajo una
     * identificación que no resolvió a ninguna persona del proyecto (o a más
     * de una). Es un aviso de la carga inicial, no un filtro de la bandeja.
     *
     * @var array{identificacion: string, tipo: ?string, ambigua: bool}|null
     */
    #[Locked]
    public ?array $avisoSinPersona = null;

    #[Locked]
    public bool $puedeCrearPersona = false;

    public function mount(): void
    {
        $identificacion = trim((string) request()->query('sin_persona', ''));
        if ($identificacion === '' || preg_match('/^[\p{L}\p{N}.\-_ ]{1,50}$/u', $identificacion) !== 1) {
            return;
        }

        $tipo = strtoupper(trim((string) request()->query('tipo', '')));
        $this->avisoSinPersona = [
            'identificacion' => $identificacion,
            'tipo' => preg_match('/^[A-Z0-9_]{1,10}$/', $tipo) === 1 ? $tipo : null,
            'ambigua' => (string) request()->query('ambigua', '') === '1',
        ];

        $proyectoId = (int) app('tenancy.proyecto_activo')->id;
        $this->puedeCrearPersona = $this->usuario()->tienePermiso('personas.crear', $proyectoId);
    }

    public function updatedEstadoFiltro(): void
    {
        $this->resetPage();
    }

    public function updatedBusqueda(): void
    {
        $this->resetPage();
    }

    /**
     * El asesor toma una cuenta del montón sin dueño.
     *
     * La pestaña «sin dueño» sólo existe para esto: con 5.000 cuentas en el
     * proyecto, encontrarlas por el listado y filtrar era el camino largo.
     */
    public function tomarCuenta(int $casoId, AutoasignarCaso $autoasignar): void
    {
        $proyectoId = (int) app('tenancy.proyecto_activo')->id;

        abort_unless(
            $this->usuario()->tienePermiso('asignaciones.autoasignarse', $proyectoId),
            403,
            'No tienes permiso para tomar cuentas en este proyecto.',
        );

        try {
            $autoasignar->execute(
                proyectoId: $proyectoId,
                casoId: $casoId,
                usuarioId: (int) auth()->id(),
                ahora: new DateTimeImmutable,
                carterasPermitidas: $this->usuario()->carterasPermitidas($proyectoId),
            );
            $this->mensajeExito = __('asignaciones.taken');
        } catch (AutoasignacionNoPermitida $e) {
            $this->addError('asignacion', $e->getMessage());
        }
    }

    public function cerrarAsignacion(int $asignacionId, CerrarAsignacion $useCase): void
    {
        $proyectoId = (int) app('tenancy.proyecto_activo')->id;
        $usuarioId = (int) auth()->id();

        $asignacion = DB::table('asignaciones')
            ->whereIn('caso_id', CarterasOperativas::casos(DB::connection(), $proyectoId)->select('c.id'))
            ->where('id', $asignacionId)
            ->where('proyecto_id', $proyectoId)
            ->where('usuario_id', $usuarioId)
            ->first();

        abort_unless($asignacion, 404);

        try {
            $useCase->execute($asignacionId, new DateTimeImmutable);
            $this->mensajeExito = 'Asignación cerrada.';
        } catch (TransicionAsignacionInvalida $e) {
            $this->addError('asignacion', $e->getMessage());
        }
    }

    public function render(): View
    {
        $proyectoActivo = app('tenancy.proyecto_activo');
        $proyectoId = (int) $proyectoActivo->id;
        $usuarioId = (int) auth()->id();

        $autoasignar = app(AutoasignarCaso::class);
        $puedeTomar = $this->usuario()->tienePermiso('asignaciones.autoasignarse', $proyectoId)
            && $autoasignar->proyectoLoPermite($proyectoId);

        if ($this->estadoFiltro === self::POOL && $puedeTomar) {
            return $this->renderPool($proyectoActivo, $proyectoId);
        }

        $query = DB::table('asignaciones as a')
            ->join('casos as c', 'c.id', '=', 'a.caso_id')
            ->join('personas as pe', 'pe.id', '=', 'c.persona_id')
            ->join('carteras as ca', 'ca.id', '=', 'c.cartera_id')
            ->join('estados_caso as ec', 'ec.id', '=', 'c.estado_caso_id')
            ->leftJoin('resultados as ru', 'ru.id', '=', 'c.resultado_ultima_gestion_id')
            ->where('a.proyecto_id', $proyectoId)
            ->where('a.usuario_id', $usuarioId)
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
                'ec.nombre as estado_caso_nombre', 'ec.codigo as estado_caso_codigo',
                'ca.nombre as cartera_nombre',
                'ru.nombre as resultado_ultimo',
            ])
            ->orderByDesc('a.prioridad')
            ->orderByDesc('c.fecha_ultima_gestion')
            ->paginate(20);

        $conteoPorEstado = DB::table('asignaciones')
            ->whereIn('caso_id', CarterasOperativas::casos(DB::connection(), $proyectoId)->select('c.id'))
            ->where('proyecto_id', $proyectoId)
            ->where('usuario_id', $usuarioId)
            ->selectRaw('estado, count(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        return view('asignaciones::livewire.bandeja', [
            'asignaciones' => $asignaciones,
            'conteoPorEstado' => $conteoPorEstado,
            'totalGeneral' => (int) $conteoPorEstado->sum(),
            'proyectoActivo' => $proyectoActivo,
            'puedeTomar' => $puedeTomar,
            'totalSinDuenio' => $puedeTomar ? $this->consultaPool($proyectoId)->count() : 0,
            'viendoPool' => false,
        ]);
    }

    /**
     * Las cuentas del proyecto que no son de nadie.
     *
     * Se respeta el límite por cartera del rol (F22): un asesor limitado a una
     * cartera no puede tomar cuentas de otra por la puerta de atrás.
     */
    private function consultaPool(int $proyectoId): Builder
    {
        $consulta = DB::table('casos as c')
            ->where('c.proyecto_id', $proyectoId)
            ->whereNull('c.eliminada_en')
            ->where(fn ($q) => CarterasOperativas::filtrar($q))
            ->whereNotExists(fn (Builder $q) => $q
                ->from('asignaciones as asg')
                ->whereColumn('asg.caso_id', 'c.id')
                ->where('asg.proyecto_id', $proyectoId));

        $carteras = $this->usuario()->carterasPermitidas($proyectoId);

        if ($carteras !== null) {
            $consulta->whereIn('c.cartera_id', $carteras);
        }

        return $consulta;
    }

    private function renderPool(object $proyectoActivo, int $proyectoId): View
    {
        $query = $this->consultaPool($proyectoId)
            ->join('personas as pe', 'pe.id', '=', 'c.persona_id')
            ->join('carteras as ca', 'ca.id', '=', 'c.cartera_id')
            ->join('estados_caso as ec', 'ec.id', '=', 'c.estado_caso_id')
            ->leftJoin('resultados as ru', 'ru.id', '=', 'c.resultado_ultima_gestion_id');

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

        $cuentas = $query
            ->select([
                'c.id as caso_id', 'c.public_id as caso_public_id', 'c.tipo_caso',
                'c.prioridad', 'c.fecha_ultima_gestion', 'c.tiene_compromiso_vigente',
                'pe.public_id as persona_public_id',
                'pe.identificacion', 'pe.tipo_persona',
                'pe.nombres', 'pe.apellidos', 'pe.razon_social',
                'ec.nombre as estado_caso_nombre',
                'ca.nombre as cartera_nombre',
                'ru.nombre as resultado_ultimo',
                // La tabla es la misma que la de asignaciones; estas cuentas aún
                // no tienen ninguna, así que las columnas de asignación van vacías.
            ])
            ->orderByDesc('c.prioridad')
            ->orderByDesc('c.creada_en')
            ->paginate(20);

        $conteoPorEstado = DB::table('asignaciones')
            ->whereIn('caso_id', CarterasOperativas::casos(DB::connection(), $proyectoId)->select('c.id'))
            ->where('proyecto_id', $proyectoId)
            ->where('usuario_id', (int) auth()->id())
            ->selectRaw('estado, count(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        return view('asignaciones::livewire.bandeja', [
            'asignaciones' => $cuentas,
            'conteoPorEstado' => $conteoPorEstado,
            'totalGeneral' => (int) $conteoPorEstado->sum(),
            'proyectoActivo' => $proyectoActivo,
            'puedeTomar' => true,
            'totalSinDuenio' => $cuentas->total(),
            'viendoPool' => true,
        ]);
    }

    private function usuario(): User
    {
        $usuario = auth()->user();
        abort_unless($usuario instanceof User, 401);

        return $usuario;
    }
}
