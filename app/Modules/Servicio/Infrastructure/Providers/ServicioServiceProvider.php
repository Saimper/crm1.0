<?php

declare(strict_types=1);

namespace App\Modules\Servicio\Infrastructure\Providers;

use App\Modules\Gestiones\Domain\Events\GestionRegistrada;
use App\Modules\Servicio\Application\Listeners\CrearAccionDesdeGestion;
use App\Modules\Servicio\Domain\Contracts\CasoServicioRepository;
use App\Modules\Servicio\Domain\Contracts\CompromisoAccionServicioRepository;
use App\Modules\Servicio\Infrastructure\Http\Livewire\ResolverAccion;
use App\Modules\Servicio\Infrastructure\Persistence\Repositories\EloquentCasoServicioRepository;
use App\Modules\Servicio\Infrastructure\Persistence\Repositories\EloquentCompromisoAccionServicioRepository;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class ServicioServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CasoServicioRepository::class, EloquentCasoServicioRepository::class);
        $this->app->bind(CompromisoAccionServicioRepository::class, EloquentCompromisoAccionServicioRepository::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(resource_path('views/modules/servicio'), 'servicio');

        Livewire::component('servicio.resolver-accion', ResolverAccion::class);

        Event::listen(GestionRegistrada::class, CrearAccionDesdeGestion::class);
    }
}
