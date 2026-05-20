<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Infrastructure\Providers;

use App\Modules\Importaciones\Application\Console\Commands\VerificarImportacionesCommand;
use App\Modules\Importaciones\Domain\Contracts\CampoPersonalizadoImportacionRepository;
use App\Modules\Importaciones\Domain\Contracts\ImportacionRepository;
use App\Modules\Importaciones\Infrastructure\Http\Livewire\Importar;
use App\Modules\Importaciones\Infrastructure\Http\Livewire\ImportarCasos;
use App\Modules\Importaciones\Infrastructure\Http\Livewire\ImportarPersonas;
use App\Modules\Importaciones\Infrastructure\Persistence\Repositories\EloquentCampoPersonalizadoImportacionRepository;
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
        $this->app->bind(CampoPersonalizadoImportacionRepository::class, EloquentCampoPersonalizadoImportacionRepository::class);
    }

    public function boot(): void
    {
        View::addNamespace('importaciones', resource_path('views/modules/importaciones'));
        Livewire::component('importaciones.importar', Importar::class);
        // Deprecated F35-B: reemplazados por importaciones.importar (wizard unificado).
        Livewire::component('importaciones.importar-personas', ImportarPersonas::class);
        Livewire::component('importaciones.importar-casos', ImportarCasos::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                VerificarImportacionesCommand::class,
            ]);
        }
    }
}
