<?php

declare(strict_types=1);

namespace App\Modules\Cx\Infrastructure\Http\Livewire;

use App\Modules\Compromisos\Application\DTOs\ResolverCompromisoInput;
use App\Modules\Cx\Application\UseCases\CancelarResolucion;
use App\Modules\Cx\Application\UseCases\MarcarResolucionCumplida;
use App\Modules\Cx\Application\UseCases\MarcarResolucionRota;
use DateTimeImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Throwable;

/**
 * Controles de resolución de un compromiso de resolución de ticket vigente.
 * Mantiene tres caminos (cumplida/rota/cancelada) y delega a los wrappers de CX.
 */
final class ResolverResolucion extends Component
{
    public int $compromisoId = 0;

    public string $accion = '';

    public string $fechaResolucion = '';

    public bool $modalAbierto = false;

    public function mount(int $compromisoId): void
    {
        $this->compromisoId    = $compromisoId;
        $this->fechaResolucion = (new DateTimeImmutable())->format('Y-m-d');
    }

    public function abrir(string $accion): void
    {
        if (! in_array($accion, ['cumplida', 'rota', 'cancelada'], true)) {
            return;
        }

        $this->accion       = $accion;
        $this->modalAbierto = true;
    }

    public function cerrar(): void
    {
        $this->modalAbierto = false;
        $this->accion       = '';
        $this->resetErrorBag();
    }

    public function confirmar(
        MarcarResolucionCumplida $cumplida,
        MarcarResolucionRota $rota,
        CancelarResolucion $cancelada,
    ): void {
        $this->validate([
            'fechaResolucion' => ['required', 'date'],
            'accion'          => ['required', 'in:cumplida,rota,cancelada'],
        ]);

        $input = new ResolverCompromisoInput(
            compromisoId:    $this->compromisoId,
            fechaResolucion: new DateTimeImmutable($this->fechaResolucion),
        );

        try {
            match ($this->accion) {
                'cumplida'  => $cumplida->execute($input),
                'rota'      => $rota->execute($input),
                'cancelada' => $cancelada->execute($input),
            };
        } catch (Throwable $e) {
            $this->addError('accion', $e->getMessage());
            return;
        }

        $this->cerrar();
        $this->dispatch('compromiso-resuelto');
        session()->flash('resolucion-resuelta', 'Resolución actualizada.');
    }

    public function render(): View
    {
        return view('cx::livewire.resolver-resolucion');
    }
}
