<?php

declare(strict_types=1);

namespace App\Modules\Asignaciones\Infrastructure\Providers;

use App\Modules\Asignaciones\Application\Listeners\IniciarTrabajoDesdeGestion;
use App\Modules\Asignaciones\Domain\Contracts\AsignacionRepository;
use App\Modules\Asignaciones\Infrastructure\Http\Livewire\AsignarMasivamente;
use App\Modules\Asignaciones\Infrastructure\Http\Livewire\Bandeja;
use App\Modules\Asignaciones\Infrastructure\Http\Livewire\BandejaEquipo;
use App\Modules\Asignaciones\Infrastructure\Http\Livewire\ReasignarEntreEquipos;
use App\Modules\Asignaciones\Infrastructure\Persistence\Repositories\EloquentAsignacionRepository;
use App\Modules\Gestiones\Domain\Events\GestionRegistrada;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class AsignacionesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AsignacionRepository::class, EloquentAsignacionRepository::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(resource_path('views/modules/asignaciones'), 'asignaciones');

        Livewire::component('asignaciones.bandeja', Bandeja::class);
        Livewire::component('asignaciones.asignar-masivamente', AsignarMasivamente::class);
        Livewire::component('asignaciones.bandeja-equipo', BandejaEquipo::class);
        Livewire::component('asignaciones.reasignar-entre-equipos', ReasignarEntreEquipos::class);

        Event::listen(GestionRegistrada::class, IniciarTrabajoDesdeGestion::class);
    }
}
