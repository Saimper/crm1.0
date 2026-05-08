<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Http\Livewire;

use App\Modules\Tenancy\Domain\ConfiguracionProyecto\AvanceConfiguracion;
use App\Modules\Tenancy\Domain\ConfiguracionProyecto\CalculadorAvanceConfiguracion;
use App\Modules\Tenancy\Domain\ConfiguracionProyecto\Exceptions\SaltoDePasoNoPermitido;
use App\Modules\Tenancy\Domain\ConfiguracionProyecto\PasoConfiguracion;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\ProyectoModel;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Configurador de proyecto. Soporta dos modos:
 *  - `wizard` (default): stepper secuencial, bloqueo por dependencia,
 *    auto-avance y botones Anterior/Siguiente. Acción final en RESUMEN.
 *  - `edicion`: tabs libres, sin lock ni auto-avance, sin Anterior/Siguiente.
 *    RESUMEN navegable como tab más, sin acción final.
 *
 * El modo se determina por la flag explícita pasada desde el closure de la
 * ruta al view (cada ruta declara su modo). Como fallback, detecta el route
 * name actual.
 *
 * `$avance` se expone como propiedad calculada (#[Computed]) — el VO Domain
 * `AvanceConfiguracion` es `final readonly` con propiedades privadas y no es
 * hidratable directamente por Livewire.
 */
final class ConfiguradorProyecto extends Component
{
    public ProyectoModel $proyecto;

    public PasoConfiguracion $pasoActivo;

    public string $modo = 'wizard';

    /**
     * Bindea el query string `?paso=...` para deep-link al modo edición.
     * En modo wizard se ignora.
     */
    #[Url(as: 'paso', except: '')]
    public string $pasoQuery = '';

    public function mount(ProyectoModel $proyecto, CalculadorAvanceConfiguracion $calculador, ?string $modo = null): void
    {
        $this->autorizar();

        $this->proyecto = $proyecto;
        $this->modo = $this->resolverModo($modo);

        if ($this->modo === 'edicion') {
            $deseado = PasoConfiguracion::tryFrom($this->pasoQuery);
            $this->pasoActivo = $deseado ?? PasoConfiguracion::DATOS_PROYECTO;
        } else {
            $this->pasoActivo = $calculador->calcular((int) $proyecto->id)->pasoActual();
        }
    }

    #[Computed]
    public function avance(): AvanceConfiguracion
    {
        /** @var CalculadorAvanceConfiguracion $calculador */
        $calculador = app(CalculadorAvanceConfiguracion::class);

        return $calculador->calcular((int) $this->proyecto->id);
    }

    /**
     * Lista de pasos visibles. Hoy son los 9 cases del enum en ambos modos;
     * el método existe como punto de extensión si en el futuro algún paso
     * depende del tipo del proyecto.
     *
     * @return list<PasoConfiguracion>
     */
    #[Computed]
    public function pasosVisibles(): array
    {
        return PasoConfiguracion::cases();
    }

    public function irAPaso(string $pasoValue): void
    {
        $this->autorizar();

        $paso = PasoConfiguracion::tryFrom($pasoValue);
        if ($paso === null) {
            return;
        }

        if ($this->modo === 'wizard' && ! $this->avance->puedeSaltarA($paso)) {
            session()->flash(
                'configurador-error',
                SaltoDePasoNoPermitido::haciaPaso($paso)->getMessage(),
            );

            return;
        }

        $this->pasoActivo = $paso;
    }

    public function siguiente(): void
    {
        $this->autorizar();
        $this->pasoActivo = $this->pasoActivo->siguiente() ?? $this->pasoActivo;
    }

    public function anterior(): void
    {
        $this->autorizar();
        $this->pasoActivo = $this->pasoActivo->anterior() ?? $this->pasoActivo;
    }

    public function refrescarAvance(): void
    {
        $this->autorizar();
        unset($this->avance);
    }

    #[On('configuracion-paso-completado')]
    public function alCompletarPaso(): void
    {
        $this->autorizar();
        unset($this->avance);

        if ($this->modo !== 'wizard') {
            return;
        }

        if ($this->avance->estaCompletado($this->pasoActivo)) {
            $siguiente = $this->pasoActivo->siguiente();
            if ($siguiente !== null) {
                $this->pasoActivo = $siguiente;
            }
        }
    }

    #[On('configuracion-ir-a-paso')]
    public function irAPasoPorEvento(string $paso): void
    {
        $this->autorizar();

        $destino = PasoConfiguracion::tryFrom($paso);
        if ($destino === null) {
            return;
        }

        if ($this->modo === 'wizard' && ! $this->avance->puedeSaltarA($destino)) {
            return;
        }

        $this->pasoActivo = $destino;
    }

    public function render(): View
    {
        return view('livewire.tenancy.configurador-proyecto', [
            'pasos' => $this->pasosVisibles,
        ]);
    }

    private function resolverModo(?string $modo): string
    {
        if ($modo !== null && in_array($modo, ['wizard', 'edicion'], true)) {
            return $modo;
        }

        if (request()->routeIs('admin.proyectos.configurar.editar')) {
            return 'edicion';
        }

        return 'wizard';
    }

    /**
     * Defensa en profundidad (patrón F23). Aunque la ruta tenga
     * `can:proyectos.configurar`, re-valida en cada acción para bloquear
     * invocaciones directas de Livewire desde contextos no protegidos.
     */
    private function autorizar(): void
    {
        $user = auth()->user();
        if ($user === null) {
            abort(403);
        }
        if ($user->esAdminGlobal()) {
            return;
        }
        if (! $user->tienePermiso('proyectos.configurar')) {
            abort(403, 'No autorizado para configurar el proyecto.');
        }
    }
}
