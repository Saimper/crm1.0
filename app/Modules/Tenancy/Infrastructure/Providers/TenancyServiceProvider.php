<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Providers;

use App\Modules\Tenancy\Domain\Contracts\CarteraRepository;
use App\Modules\Tenancy\Domain\Contracts\MandanteRepository;
use App\Modules\Tenancy\Domain\Contracts\ProyectoRepository;
use App\Modules\Tenancy\Infrastructure\Http\Livewire\AdminMandantes;
use App\Modules\Tenancy\Infrastructure\Http\Livewire\AdminProyectos;
use App\Modules\Tenancy\Infrastructure\Http\Livewire\SelectorProyecto;
use App\Modules\Tenancy\Infrastructure\Http\Middleware\ResolverProyectoActivo;
use App\Modules\Tenancy\Infrastructure\Persistence\Repositories\EloquentCarteraRepository;
use App\Modules\Tenancy\Infrastructure\Persistence\Repositories\EloquentMandanteRepository;
use App\Modules\Tenancy\Infrastructure\Persistence\Repositories\EloquentProyectoRepository;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(MandanteRepository::class, EloquentMandanteRepository::class);
        $this->app->bind(ProyectoRepository::class, EloquentProyectoRepository::class);
        $this->app->bind(CarteraRepository::class, EloquentCarteraRepository::class);
    }

    public function boot(Router $router): void
    {
        $this->loadViewsFrom(resource_path('views/modules/tenancy'), 'tenancy');

        $router->aliasMiddleware('proyecto.activo', ResolverProyectoActivo::class);

        // Persistent middleware: se ejecuta también en /livewire/update para que
        // `tenancy.proyecto_activo` siga bindeado al disparar acciones de Livewire
        // desde una página dentro de /proyectos/{id}/... (extrae el id del Referer).
        Livewire::addPersistentMiddleware([
            ResolverProyectoActivo::class,
        ]);

        Livewire::component('tenancy.selector-proyecto', SelectorProyecto::class);
        Livewire::component('tenancy.admin-mandantes', AdminMandantes::class);
        Livewire::component('tenancy.admin-proyectos', AdminProyectos::class);
    }
}
