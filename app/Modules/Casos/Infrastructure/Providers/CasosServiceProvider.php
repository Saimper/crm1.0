<?php

declare(strict_types=1);

namespace App\Modules\Casos\Infrastructure\Providers;

use App\Modules\Casos\Application\Listeners\ActivarBanderaCompromisoVigente;
use App\Modules\Casos\Application\Listeners\ActualizarDesnormalizadosDesdeGestion;
use App\Modules\Casos\Application\Listeners\RecalcularBanderaCompromisoVigente;
use App\Modules\Casos\Domain\Contracts\CasoRepository;
use App\Modules\Casos\Infrastructure\Http\Livewire\NuevaGestion;
use App\Modules\Casos\Infrastructure\Http\Livewire\VistaDeTrabajo;
use App\Modules\Casos\Infrastructure\Persistence\Repositories\EloquentCasoRepository;
use App\Modules\Compromisos\Domain\Events\CompromisoCancelado;
use App\Modules\Compromisos\Domain\Events\CompromisoCreado;
use App\Modules\Compromisos\Domain\Events\CompromisoCumplido;
use App\Modules\Compromisos\Domain\Events\CompromisoRoto;
use App\Modules\Gestiones\Domain\Events\GestionRegistrada;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class CasosServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CasoRepository::class, EloquentCasoRepository::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(resource_path('views/modules/casos'), 'casos');

        Livewire::component('casos.vista-de-trabajo', VistaDeTrabajo::class);
        Livewire::component('casos.nueva-gestion', NuevaGestion::class);

        Event::listen(GestionRegistrada::class, ActualizarDesnormalizadosDesdeGestion::class);
        Event::listen(CompromisoCreado::class, ActivarBanderaCompromisoVigente::class);
        Event::listen(CompromisoCumplido::class, RecalcularBanderaCompromisoVigente::class);
        Event::listen(CompromisoRoto::class, RecalcularBanderaCompromisoVigente::class);
        Event::listen(CompromisoCancelado::class, RecalcularBanderaCompromisoVigente::class);
    }
}
