<?php

declare(strict_types=1);

namespace App\Modules\Casos\Infrastructure\Http\Livewire;

use App\Models\User;
use App\Modules\Casos\Application\Services\ConsultaHistorico;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** A read-only screen: it deliberately has no mutation or operational child components. */
final class FichaHistorica extends Component
{
    use WithPagination;

    #[Locked]
    public string $casoPublicId = '';

    #[Url(except: '')]
    public string $archivo = '';

    public function mount(string $caso): void
    {
        $this->casoPublicId = $caso;
    }

    public function updatedArchivo(): void
    {
        $this->resetPage('gestionesPage');
        $this->resetPage('compromisosPage');
    }

    public function render(): View
    {
        $proyectoId = (int) app('tenancy.proyecto_activo')->id;
        $usuario = auth()->user();
        abort_unless($usuario instanceof User && $usuario->tienePermiso('historico.ver', $proyectoId), 403);
        $consulta = app(ConsultaHistorico::class);
        $permitidas = $usuario->carterasPermitidasParaPermiso('historico.ver', $proyectoId);
        $cuenta = $consulta->ficha($proyectoId, $permitidas, $this->casoPublicId, $this->archivo);
        abort_unless($cuenta !== null, 404);
        $base = $consulta->cuentas($proyectoId, $permitidas, caso: $this->casoPublicId, archivo: $this->archivo)
            ->where('h.cursor_id', $cuenta->cursor_id);

        return view('casos::livewire.ficha-historica', [
            'cuenta' => $cuenta,
            'proyectoId' => $proyectoId,
            'gestiones' => $consulta->gestiones(clone $base, $proyectoId)->orderByDesc('g.creada_en')->orderByDesc('g.id')->paginate(20, pageName: 'gestionesPage'),
            'compromisos' => $consulta->compromisos(clone $base, $proyectoId)->orderByDesc('co.creada_en')->orderByDesc('co.id')->paginate(20, pageName: 'compromisosPage'),
            'filtros' => array_filter(['caso' => $this->casoPublicId, 'archivo' => $this->archivo]),
        ]);
    }
}
