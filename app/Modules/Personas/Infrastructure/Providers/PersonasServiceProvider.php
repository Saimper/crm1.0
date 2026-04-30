<?php

declare(strict_types=1);

namespace App\Modules\Personas\Infrastructure\Providers;

use App\Modules\Personas\Domain\Contracts\PersonaRepository;
use App\Modules\Personas\Infrastructure\Http\Livewire\BuscadorGlobal;
use App\Modules\Personas\Infrastructure\Http\Livewire\CrearPersona;
use App\Modules\Personas\Infrastructure\Http\Livewire\ListadoPersonas;
use App\Modules\Personas\Infrastructure\Persistence\Repositories\EloquentPersonaRepository;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class PersonasServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PersonaRepository::class, EloquentPersonaRepository::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(resource_path('views/modules/personas'), 'personas');

        Livewire::component('personas.crear-persona', CrearPersona::class);
        Livewire::component('personas.buscador-global', BuscadorGlobal::class);
        Livewire::component('personas.listado-personas', ListadoPersonas::class);
    }
}
