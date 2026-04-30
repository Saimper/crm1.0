<?php

declare(strict_types=1);

namespace App\Modules\Promesas\Infrastructure\Http\Livewire;

use App\Modules\Promesas\Application\DTOs\ResolverPromesaInput;
use App\Modules\Promesas\Application\UseCases\CancelarPromesa;
use App\Modules\Promesas\Application\UseCases\MarcarPromesaCumplida;
use App\Modules\Promesas\Application\UseCases\MarcarPromesaRota;
use App\Modules\Promesas\Domain\Exceptions\TransicionPromesaInvalida;
use DateTimeImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

final class ResolverPromesa extends Component
{
    public int $promesaId;

    public string $monto = '';

    public string $moneda = '';

    public string $fechaPromesa = '';

    public ?string $accion = null;

    public string $fechaResolucion = '';

    public bool $modalAbierto = false;

    public function mount(int $promesaId, string $monto, string $moneda, string $fechaPromesa): void
    {
        $this->promesaId = $promesaId;
        $this->monto = $monto;
        $this->moneda = $moneda;
        $this->fechaPromesa = $fechaPromesa;
    }

    public function abrir(string $accion): void
    {
        if (! in_array($accion, ['cumplida', 'rota', 'cancelada'], true)) {
            return;
        }

        $this->accion = $accion;
        $this->fechaResolucion = now()->format('Y-m-d');
        $this->resetErrorBag();
        $this->modalAbierto = true;
    }

    public function cerrar(): void
    {
        $this->modalAbierto = false;
        $this->accion = null;
        $this->fechaResolucion = '';
        $this->resetErrorBag();
    }

    public function confirmar(): void
    {
        if (auth()->user()?->tienePermiso('promesas.resolver') !== true) {
            abort(403, 'No tienes permiso para resolver promesas.');
        }

        $this->validate([
            'fechaResolucion' => 'required|date|before_or_equal:today',
        ], [
            'fechaResolucion.required' => 'La fecha de resolución es obligatoria.',
            'fechaResolucion.before_or_equal' => 'La fecha no puede ser futura.',
        ]);

        $input = new ResolverPromesaInput(
            promesaId: $this->promesaId,
            fechaResolucion: new DateTimeImmutable($this->fechaResolucion),
        );

        $useCase = match ($this->accion) {
            'cumplida' => app(MarcarPromesaCumplida::class),
            'rota' => app(MarcarPromesaRota::class),
            'cancelada' => app(CancelarPromesa::class),
            default => null,
        };

        if ($useCase === null) {
            return;
        }

        try {
            $useCase->execute($input);
        } catch (TransicionPromesaInvalida $e) {
            throw ValidationException::withMessages(['general' => $e->getMessage()]);
        }

        $this->cerrar();
        $this->dispatch('promesa-resuelta');
    }

    public function render(): View
    {
        return view('promesas::livewire.resolver-promesa');
    }
}
