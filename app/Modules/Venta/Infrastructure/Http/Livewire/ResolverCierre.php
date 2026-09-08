<?php

declare(strict_types=1);

namespace App\Modules\Venta\Infrastructure\Http\Livewire;

use App\Modules\Compromisos\Application\DTOs\ResolverCompromisoInput;
use App\Modules\Venta\Application\UseCases\CancelarCierre;
use App\Modules\Venta\Application\UseCases\MarcarCierreGanado;
use App\Modules\Venta\Application\UseCases\MarcarCierrePerdido;
use App\Support\Livewire\AutorizaEnProyectoActivo;
use DateTimeImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * Controles de resolución de un cierre de venta vigente.
 * Tres caminos: ganado, perdido, cancelado (delegan a wrappers de Venta).
 */
final class ResolverCierre extends Component
{
    use AutorizaEnProyectoActivo;

    /**
     * `#[Locked]` porque el id lo fija `mount()` y el cliente no debe reapuntarlo:
     * el repositorio busca el compromiso con `sinScopeProyecto()`, así que un
     * `$wire.set('compromisoId', N)` alcanzaba el de cualquier mandante.
     */
    #[Locked]
    public int $compromisoId = 0;

    public string $accion = '';

    public string $fechaResolucion = '';

    public bool $modalAbierto = false;

    public function mount(int $compromisoId): void
    {
        $this->compromisoId = $compromisoId;
        $this->fechaResolucion = (new DateTimeImmutable)->format('Y-m-d');
    }

    public function abrir(string $accion): void
    {
        if (! in_array($accion, ['ganado', 'perdido', 'cancelado'], true)) {
            return;
        }

        $this->accion = $accion;
        $this->modalAbierto = true;
    }

    public function cerrar(): void
    {
        $this->modalAbierto = false;
        $this->accion = '';
        $this->resetErrorBag();
    }

    public function confirmar(
        MarcarCierreGanado $ganado,
        MarcarCierrePerdido $perdido,
        CancelarCierre $cancelado,
    ): void {
        $this->validate([
            'fechaResolucion' => ['required', 'date'],
            'accion' => ['required', 'in:ganado,perdido,cancelado'],
        ]);

        // Cancelar no es resolver: `compromisos.resolver` lo tiene también el
        // GESTOR y `compromisos.cancelar` sólo SUPERVISOR hacia arriba. Los dos
        // permisos existían en el seeder y no los exigía nadie.
        $this->autorizarEn($this->accion === 'cancelado' ? 'compromisos.cancelar' : 'compromisos.resolver');

        // Y que el compromiso sea de este proyecto: el permiso es por proyecto,
        // tenerlo en el propio no autoriza a tocar el del ajeno.
        $this->exigirDelProyecto('compromisos', $this->compromisoId);

        $input = new ResolverCompromisoInput(
            compromisoId: $this->compromisoId,
            fechaResolucion: new DateTimeImmutable($this->fechaResolucion),
        );

        try {
            match ($this->accion) {
                'ganado' => $ganado->execute($input),
                'perdido' => $perdido->execute($input),
                'cancelado' => $cancelado->execute($input),
            };
        } catch (Throwable $e) {
            $this->addError('accion', $e->getMessage());

            return;
        }

        $this->cerrar();
        $this->dispatch('compromiso-resuelto');
        session()->flash('cierre-resuelto', 'Cierre actualizado.');
    }

    public function render(): View
    {
        return view('venta::livewire.resolver-cierre');
    }
}
