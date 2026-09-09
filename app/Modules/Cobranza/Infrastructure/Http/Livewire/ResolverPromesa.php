<?php

declare(strict_types=1);

namespace App\Modules\Cobranza\Infrastructure\Http\Livewire;

use App\Modules\Cobranza\Application\UseCases\CancelarPromesa;
use App\Modules\Cobranza\Application\UseCases\MarcarPromesaCumplida;
use App\Modules\Cobranza\Application\UseCases\MarcarPromesaRota;
use App\Modules\Compromisos\Application\DTOs\ResolverCompromisoInput;
use App\Support\Livewire\AutorizaEnProyectoActivo;
use DateTimeImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * Controles de resolución de una promesa de pago vigente.
 * Mantiene los tres caminos (cumplida/rota/cancelada) y delega a los wrappers de cobranza.
 */
final class ResolverPromesa extends Component
{
    use AutorizaEnProyectoActivo;

    /**
     * `#[Locked]` porque el id lo fija `mount()` desde la Vista de Trabajo y el
     * cliente no debe poder reapuntarlo: sin esto, un `$wire.set('compromisoId', N)`
     * desde la consola resolvía la promesa de cualquier caso, de cualquier
     * proyecto, porque el repositorio la busca con `sinScopeProyecto()`.
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
        if (! in_array($accion, ['cumplida', 'rota', 'cancelada'], true)) {
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
        MarcarPromesaCumplida $cumplida,
        MarcarPromesaRota $rota,
        CancelarPromesa $cancelada,
    ): void {
        $this->validate([
            'fechaResolucion' => ['required', 'date'],
            'accion' => ['required', 'in:cumplida,rota,cancelada'],
        ]);

        // Cancelar no es resolver: el seeder da `compromisos.resolver` también al
        // GESTOR, y `compromisos.cancelar` sólo de SUPERVISOR hacia arriba.
        // Ambos permisos existían y no los exigía nadie.
        $this->autorizarEn($this->accion === 'cancelada' ? 'compromisos.cancelar' : 'compromisos.resolver');

        // Y que la promesa sea de este proyecto: el permiso es por proyecto, así
        // que tenerlo en el propio no autoriza a tocar el compromiso del ajeno.
        $this->exigirDelProyecto('compromisos', $this->compromisoId);

        $input = new ResolverCompromisoInput(
            compromisoId: $this->compromisoId,
            fechaResolucion: new DateTimeImmutable($this->fechaResolucion),
        );

        try {
            match ($this->accion) {
                'cumplida' => $cumplida->execute($input),
                'rota' => $rota->execute($input),
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
