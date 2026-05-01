<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Infrastructure\Providers;

use App\Modules\Importaciones\Domain\Contracts\ImportacionRepository;
use App\Modules\Importaciones\Infrastructure\Http\Livewire\ImportarCasos;
use App\Modules\Importaciones\Infrastructure\Http\Livewire\ImportarPersonas;
use App\Modules\Importaciones\Infrastructure\Persistence\Repositories\EloquentImportacionRepository;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class ImportacionesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(config_path('imports.php'), 'imports');

        $this->app->bind(ImportacionRepository::class, EloquentImportacionRepository::class);
    }

    public function boot(): void
    {
        View::addNamespace('importaciones', resource_path('views/modules/importaciones'));
        Livewire::component('importaciones.importar-personas', ImportarPersonas::class);
        Livewire::component('importaciones.importar-casos', ImportarCasos::class);
    }
}
