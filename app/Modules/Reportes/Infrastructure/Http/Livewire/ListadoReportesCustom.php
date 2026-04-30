<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Infrastructure\Http\Livewire;

use App\Modules\Reportes\Application\UseCases\EliminarDefinicionReporte;
use App\Modules\Reportes\Domain\Constructor\Contracts\RepositorioDefinicionReporte;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class ListadoReportesCustom extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()?->tienePermiso('reportes.constructor.ejecutar') === true, 403);
    }

    public function eliminar(int $id): void
    {
        abort_unless(auth()->user()?->tienePermiso('reportes.constructor.gestionar') === true, 403);

        $proyectoId = (int) app('tenancy.proyecto_activo')->id;
        app(EliminarDefinicionReporte::class)->execute($id, $proyectoId);
        session()->flash('mensaje', 'Definición eliminada.');
    }

    public function render(): View
    {
        $proyectoId = (int) app('tenancy.proyecto_activo')->id;
        $defs = app(RepositorioDefinicionReporte::class)->listarPorProyecto($proyectoId, false);

        return view('reportes::livewire.listado-reportes-custom', [
            'definiciones' => $defs,
            'puedeGestionar' => auth()->user()?->tienePermiso('reportes.constructor.gestionar') === true,
            'puedeExportar' => auth()->user()?->tienePermiso('reportes.constructor.exportar') === true,
        ]);
    }
}
