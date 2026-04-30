<?php

declare(strict_types=1);

namespace App\Modules\Asignaciones\Infrastructure\Http\Livewire;

use Illuminate\Contracts\View\View;
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

    public function updating(): void
    {
        $this->resetPage();
    }

    public function updatedEquipoId(): void
    {
        $this->miembroId = null;
        $this->resetPage();
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
                ->leftJoin('campanas as cm', 'cm.id', '=', 'a.campana_id')
                ->where('a.proyecto_id', $proyectoId)
                ->whereIn('a.usuario_id', $usuariosQuery)
                ->whereNull('c.eliminada_en');

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
                    'cm.nombre as campana_nombre',
                    'gu.id as gestor_id', 'gu.name as gestor_nombre',
                ])
                ->orderBy('gu.name')
                ->orderByDesc('a.prioridad')
                ->orderByDesc('c.fecha_ultima_gestion')
                ->paginate(25);

            $conteoPorEstado = DB::table('asignaciones')
                ->where('proyecto_id', $proyectoId)
                ->whereIn('usuario_id', $miembroIds)
                ->selectRaw('estado, count(*) as total')
                ->groupBy('estado')
                ->pluck('total', 'estado');

            $conteoPorMiembro = DB::table('asignaciones as a')
                ->join('users as u', 'u.id', '=', 'a.usuario_id')
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
        ]);
    }
}
