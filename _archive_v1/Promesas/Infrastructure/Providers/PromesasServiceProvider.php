<?php

declare(strict_types=1);

namespace App\Modules\Promesas\Infrastructure\Providers;

use App\Modules\Gestiones\Domain\Events\GestionRegistrada;
use App\Modules\Promesas\Application\Listeners\CrearPromesaAlRegistrarGestion;
use App\Modules\Promesas\Domain\Contracts\PromesaRepository;
use App\Modules\Promesas\Infrastructure\Http\Livewire\ResolverPromesa;
use App\Modules\Promesas\Infrastructure\Persistence\Repositories\EloquentPromesaRepository;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class PromesasServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PromesaRepository::class, EloquentPromesaRepository::class);
    }

    public function boot(): void
    {
        View::addNamespace('promesas', resource_path('views/modules/promesas'));
        Livewire::component('promesas.resolver-promesa', ResolverPromesa::class);

        Event::listen(GestionRegistrada::class, CrearPromesaAlRegistrarGestion::class);
    }
}
