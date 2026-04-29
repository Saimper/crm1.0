<?php

declare(strict_types=1);

namespace App\Modules\Servicio\Infrastructure\Http\Livewire;

use App\Modules\Compromisos\Application\DTOs\ResolverCompromisoInput;
use App\Modules\Servicio\Application\UseCases\CancelarAccion;
use App\Modules\Servicio\Application\UseCases\MarcarAccionEjecutada;
use App\Modules\Servicio\Application\UseCases\MarcarAccionFallida;
use DateTimeImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Throwable;

/**
 * Controles de resolución de una acción de servicio vigente.
 * Tres caminos: ejecutada, fallida, cancelada (delegan a wrappers de Servicio).
 */
final class ResolverAccion extends Component
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
        if (! in_array($accion, ['ejecutada', 'fallida', 'cancelada'], true)) {
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
        MarcarAccionEjecutada $ejecutada,
        MarcarAccionFallida $fallida,
        CancelarAccion $cancelada,
    ): void {
        $this->validate([
            'fechaResolucion' => ['required', 'date'],
            'accion'          => ['required', 'in:ejecutada,fallida,cancelada'],
        ]);

        $input = new ResolverCompromisoInput(
            compromisoId:    $this->compromisoId,
            fechaResolucion: new DateTimeImmutable($this->fechaResolucion),
        );

        try {
            match ($this->accion) {
                'ejecutada' => $ejecutada->execute($input),
                'fallida'   => $fallida->execute($input),
                'cancelada' => $cancelada->execute($input),
            };
        } catch (Throwable $e) {
            $this->addError('accion', $e->getMessage());
            return;
        }

        $this->cerrar();
        $this->dispatch('compromiso-resuelto');
        session()->flash('accion-resuelta', 'Acción actualizada.');
    }

    public function render(): View
    {
        return view('servicio::livewire.resolver-accion');
    }
}
