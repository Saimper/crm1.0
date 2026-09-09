<?php

declare(strict_types=1);

namespace App\Modules\Personas\Infrastructure\Http\Livewire;

use App\Models\User;
use App\Modules\Personas\Application\DTOs\FiltrosListadoPersonas;
use App\Modules\Personas\Application\Services\ConsultaListadoPersonas;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Listado paginado de personas del proyecto activo.
 *
 * Filtros: búsqueda libre (identificación/nombre/razón) y tipo_persona (fisica/juridica/—).
 * Permiso: personas.ver. ADMIN_GLOBAL pasa por Gate::before.
 *
 * La consulta y los filtros viven en `ConsultaListadoPersonas`, compartida con
 * la exportación: el botón «Exportar CSV» descarga exactamente lo que se ve.
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
        $filtros = FiltrosListadoPersonas::desde($this->busqueda, $this->tipoPersona);
        $consulta = app(ConsultaListadoPersonas::class);

        $carteras = $this->usuario()->carterasPermitidas($proyectoId);

        $personas = $consulta
            ->aplicarFiltros(
                $consulta->recortarACarteras($consulta->consultaBase($proyectoId), $carteras),
                $filtros,
            )
            ->select([
                'p.id', 'p.public_id', 'p.tipo_persona',
                'p.identificacion', 'p.nombres', 'p.apellidos', 'p.razon_social',
                'p.fecha_nacimiento', 'p.creada_en',
                'ti.codigo as tipo_identificacion_codigo',
                $consulta->totalCasos($carteras),
            ])
            ->orderByDesc('p.creada_en')
            ->paginate(25);

        // El mismo recorte que la lista: si la cabecera dijera «1.200 personas
        // registradas» y debajo se vieran 300, el número de arriba estaría
        // contando lo que este usuario no puede mirar.
        $totalProyecto = $consulta
            ->recortarACarteras($consulta->consultaBase($proyectoId), $carteras)
            ->count();

        return view('personas::livewire.listado-personas', [
            'personas' => $personas,
            'totalProyecto' => $totalProyecto,
            'urlExportar' => route('proyectos.personas.exportar', ['proyecto_id' => $proyectoId] + $filtros->comoParametros()),
        ]);
    }

    private function usuario(): User
    {
        $usuario = Auth::user();
        abort_unless($usuario instanceof User, 401);

        return $usuario;
    }
}
