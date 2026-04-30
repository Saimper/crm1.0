<?php

declare(strict_types=1);

namespace App\Modules\Casos\Infrastructure\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Listado paginado de casos del proyecto activo (todos los tipos CTI).
 *
 * Filtros: búsqueda libre (identificación/nombre/razón persona), cartera,
 * estado_caso, tipo_caso (cuando el proyecto soporta múltiples — actualmente
 * un proyecto = un tipo).
 *
 * Permiso: casos.ver.
 */
final class ListadoCasos extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $busqueda = '';

    #[Url(as: 'cartera', except: '')]
    public string $carteraId = '';

    #[Url(as: 'estado', except: '')]
    public string $estadoCasoId = '';

    public function updatingBusqueda(): void
    {
        $this->resetPage();
    }

    public function updatingCarteraId(): void
    {
        $this->resetPage();
    }

    public function updatingEstadoCasoId(): void
    {
        $this->resetPage();
    }

    public function limpiarFiltros(): void
    {
        $this->busqueda = '';
        $this->carteraId = '';
        $this->estadoCasoId = '';
        $this->resetPage();
    }

    public function render(): View
    {
        $proyectoId = (int) app('tenancy.proyecto_activo')->id;
        $busqueda = trim($this->busqueda);

        $q = DB::table('casos as c')
            ->join('personas as p', 'p.id', '=', 'c.persona_id')
            ->leftJoin('carteras as ca', 'ca.id', '=', 'c.cartera_id')
            ->leftJoin('estados_caso as ec', 'ec.id', '=', 'c.estado_caso_id')
            ->where('c.proyecto_id', $proyectoId)
            ->whereNull('c.eliminada_en');

        if ($busqueda !== '') {
            $like = '%'.$busqueda.'%';
            $q->where(function ($w) use ($like): void {
                $w->where('p.identificacion', 'like', $like)
                    ->orWhere('p.nombres', 'like', $like)
                    ->orWhere('p.apellidos', 'like', $like)
                    ->orWhere('p.razon_social', 'like', $like);
            });
        }

        if ($this->carteraId !== '' && ctype_digit($this->carteraId)) {
            $q->where('c.cartera_id', (int) $this->carteraId);
        }

        if ($this->estadoCasoId !== '' && ctype_digit($this->estadoCasoId)) {
            $q->where('c.estado_caso_id', (int) $this->estadoCasoId);
        }

        $casos = $q
            ->select([
                'c.id', 'c.public_id', 'c.tipo_caso', 'c.prioridad',
                'c.fecha_ultima_gestion', 'c.tiene_compromiso_vigente',
                'p.public_id as persona_public_id', 'p.tipo_persona',
                'p.identificacion', 'p.nombres', 'p.apellidos', 'p.razon_social',
                'ca.nombre as cartera_nombre', 'ca.codigo as cartera_codigo',
                'ec.nombre as estado_caso_nombre', 'ec.codigo as estado_caso_codigo',
            ])
            ->orderByDesc('c.prioridad')
            ->orderByDesc('c.creada_en')
            ->paginate(25);

        $carteras = DB::table('carteras')
            ->where('proyecto_id', $proyectoId)
            ->whereNull('eliminada_en')
            ->where('activo', true)
            ->orderBy('nombre')
            ->select(['id', 'nombre', 'codigo'])
            ->get();

        $estados = DB::table('estados_caso')
            ->where('proyecto_id', $proyectoId)
            ->where('activo', true)
            ->orderBy('orden')
            ->select(['id', 'nombre', 'codigo'])
            ->get();

        $totalProyecto = (int) DB::table('casos')
            ->where('proyecto_id', $proyectoId)
            ->whereNull('eliminada_en')
            ->count();

        return view('casos::livewire.listado-casos', [
            'casos' => $casos,
            'carteras' => $carteras,
            'estados' => $estados,
            'totalProyecto' => $totalProyecto,
        ]);
    }
}
