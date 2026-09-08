<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Infrastructure\Providers;

use App\Modules\Importaciones\Application\Console\Commands\PurgarImportacionesObsoletasCommand;
use App\Modules\Importaciones\Application\Console\Commands\PurgarPayloadsCommand;
use App\Modules\Importaciones\Application\Console\Commands\PurgarSubidasTemporalesCommand;
use App\Modules\Importaciones\Application\Console\Commands\RepararEncodingCommand;
use App\Modules\Importaciones\Application\Console\Commands\RescatarCamposNativosCommand;
use App\Modules\Importaciones\Application\Console\Commands\VerificarImportacionesCommand;
use App\Modules\Importaciones\Domain\Contracts\CampoPersonalizadoImportacionRepository;
use App\Modules\Importaciones\Domain\Contracts\ImportacionRepository;
use App\Modules\Importaciones\Infrastructure\Http\Livewire\Importar;
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

        if ($this->app->runningInConsole()) {
            $this->commands([
                PurgarImportacionesObsoletasCommand::class,
                PurgarPayloadsCommand::class,
                PurgarSubidasTemporalesCommand::class,
                RepararEncodingCommand::class,
                RescatarCamposNativosCommand::class,
                VerificarImportacionesCommand::class,
            ]);
        }
    }
}
