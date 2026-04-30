<?php

declare(strict_types=1);

namespace App\Modules\Personas\Infrastructure\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Listado paginado de personas del proyecto activo.
 *
 * Filtros: búsqueda libre (identificación/nombre/razón) y tipo_persona (fisica/juridica/—).
 * Permiso: personas.ver. ADMIN_GLOBAL pasa por Gate::before.
 */
final class ListadoPersonas extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $busqueda = '';

    #[Url(as: 'tipo', except: '')]
    public string $tipoPersona = '';

    public function updatingBusqueda(): void
    {
        $this->resetPage();
    }

    public function updatingTipoPersona(): void
    {
        $this->resetPage();
    }

    public function limpiarFiltros(): void
    {
        $this->busqueda = '';
        $this->tipoPersona = '';
        $this->resetPage();
    }

    public function render(): View
    {
        $proyectoId = (int) app('tenancy.proyecto_activo')->id;
        $busqueda = trim($this->busqueda);

        $q = DB::table('personas as p')
            ->leftJoin('tipos_identificacion as ti', 'ti.id', '=', 'p.tipo_identificacion_id')
            ->leftJoin('casos as c', function ($join): void {
                $join->on('c.persona_id', '=', 'p.id')->whereNull('c.eliminada_en');
            })
            ->where('p.proyecto_id', $proyectoId)
            ->whereNull('p.eliminada_en');

        if ($busqueda !== '') {
            $like = '%'.$busqueda.'%';
            $q->where(function ($w) use ($like): void {
                $w->where('p.identificacion', 'like', $like)
                    ->orWhere('p.nombres', 'like', $like)
                    ->orWhere('p.apellidos', 'like', $like)
                    ->orWhere('p.razon_social', 'like', $like);
            });
        }

        if (in_array($this->tipoPersona, ['fisica', 'juridica'], true)) {
            $q->where('p.tipo_persona', $this->tipoPersona);
        }

        $personas = $q
            ->select([
                'p.id', 'p.public_id', 'p.tipo_persona',
                'p.identificacion', 'p.nombres', 'p.apellidos', 'p.razon_social',
                'p.fecha_nacimiento', 'p.creada_en',
                'ti.codigo as tipo_identificacion_codigo',
                DB::raw('count(c.id) as total_casos'),
            ])
            ->groupBy([
                'p.id', 'p.public_id', 'p.tipo_persona',
                'p.identificacion', 'p.nombres', 'p.apellidos', 'p.razon_social',
                'p.fecha_nacimiento', 'p.creada_en',
                'ti.codigo',
            ])
            ->orderByDesc('p.creada_en')
            ->paginate(25);

        $totalProyecto = (int) DB::table('personas')
            ->where('proyecto_id', $proyectoId)
            ->whereNull('eliminada_en')
            ->count();

        return view('personas::livewire.listado-personas', [
            'personas' => $personas,
            'totalProyecto' => $totalProyecto,
        ]);
    }
}
