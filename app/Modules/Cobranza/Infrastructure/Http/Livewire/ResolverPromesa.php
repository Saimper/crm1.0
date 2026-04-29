<?php

declare(strict_types=1);

namespace App\Modules\Cobranza\Infrastructure\Http\Livewire;

use App\Modules\Cobranza\Application\UseCases\CancelarPromesa;
use App\Modules\Cobranza\Application\UseCases\MarcarPromesaCumplida;
use App\Modules\Cobranza\Application\UseCases\MarcarPromesaRota;
use App\Modules\Compromisos\Application\DTOs\ResolverCompromisoInput;
use DateTimeImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Throwable;

/**
 * Controles de resolución de una promesa de pago vigente.
 * Mantiene los tres caminos (cumplida/rota/cancelada) y delega a los wrappers de cobranza.
 */
final class ResolverPromesa extends Component
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
        MarcarPromesaCumplida $cumplida,
        MarcarPromesaRota $rota,
        CancelarPromesa $cancelada,
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
        session()->flash('promesa-resuelta', 'Compromiso resuelto.');
    }

    public function render(): View
    {
        return view('cobranza::livewire.resolver-promesa');
    }
}
