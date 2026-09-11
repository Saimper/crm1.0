<?php

declare(strict_types=1);

namespace App\Modules\Servicio\Infrastructure\Http\Livewire;

use App\Modules\Compromisos\Application\DTOs\ResolverCompromisoInput;
use App\Modules\Servicio\Application\UseCases\CancelarAccion;
use App\Modules\Servicio\Application\UseCases\MarcarAccionEjecutada;
use App\Modules\Servicio\Application\UseCases\MarcarAccionFallida;
use App\Modules\Tenancy\Application\Services\RelojDelMandante;
use App\Support\Livewire\AutorizaCompromisoOperativo;
use App\Support\Livewire\AutorizaEnProyectoActivo;
use DateTimeImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * Controles de resolución de una acción de servicio vigente.
 * Tres caminos: ejecutada, fallida, cancelada (delegan a wrappers de Servicio).
 */
final class ResolverAccion extends Component
{
    use AutorizaCompromisoOperativo;
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
        $this->fechaResolucion = app(RelojDelMandante::class)->hoy();
    }

    public function abrir(string $accion): void
    {
        if (! in_array($accion, ['ejecutada', 'fallida', 'cancelada'], true)) {
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
        MarcarAccionEjecutada $ejecutada,
        MarcarAccionFallida $fallida,
        CancelarAccion $cancelada,
    ): void {
        $this->validate([
            'fechaResolucion' => ['required', 'date'],
            'accion' => ['required', 'in:ejecutada,fallida,cancelada'],
        ]);

        // Cancelar no es resolver: `compromisos.resolver` lo tiene también el
        // GESTOR y `compromisos.cancelar` sólo SUPERVISOR hacia arriba. Los dos
        // permisos existían en el seeder y no los exigía nadie.
        $this->autorizarEn($this->accion === 'cancelada' ? 'compromisos.cancelar' : 'compromisos.resolver');

        // Y que el compromiso sea de este proyecto: el permiso es por proyecto,
        // tenerlo en el propio no autoriza a tocar el del ajeno.
        $this->exigirCompromisoOperativo($this->compromisoId, $this->accion === 'cancelada' ? 'compromisos.cancelar' : 'compromisos.resolver');

        $input = new ResolverCompromisoInput(
            compromisoId: $this->compromisoId,
            proyectoId: $this->proyectoActivoId(),
            fechaResolucion: new DateTimeImmutable($this->fechaResolucion),
        );

        try {
            match ($this->accion) {
                'ejecutada' => $ejecutada->execute($input),
                'fallida' => $fallida->execute($input),
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
