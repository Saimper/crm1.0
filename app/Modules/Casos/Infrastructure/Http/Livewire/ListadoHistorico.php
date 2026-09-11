<?php

declare(strict_types=1);

namespace App\Modules\Casos\Infrastructure\Http\Livewire;

use App\Models\User;
use App\Modules\Casos\Application\Services\ConsultaHistorico;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

final class ListadoHistorico extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $busqueda = '';

    #[Url(except: '')]
    public string $cartera = '';

    public function updatedBusqueda(): void
    {
        $this->resetPage();
    }

    public function updatedCartera(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $proyectoId = (int) app('tenancy.proyecto_activo')->id;
        $usuario = auth()->user();
        abort_unless($usuario instanceof User && $usuario->tienePermiso('historico.ver', $proyectoId), 403);
        $consulta = app(ConsultaHistorico::class);
        $permitidas = $usuario->carterasPermitidasParaPermiso('historico.ver', $proyectoId);
        $base = $consulta->cuentas($proyectoId, $permitidas, $this->busqueda, $this->cartera);

        return view('casos::livewire.listado-historico', [
            'proyectoId' => $proyectoId,
            'cuentas' => $base->orderByDesc('h.fecha_archivo')->orderBy('h.cursor_id')->paginate(25),
            'carteras' => $consulta->cuentas($proyectoId, $permitidas)->select('h.cartera_public_id', 'h.cartera_nombre')->distinct()->orderBy('h.cartera_nombre')->get(),
            'filtros' => array_filter(['q' => trim($this->busqueda), 'cartera' => $this->cartera]),
        ]);
    }
}
