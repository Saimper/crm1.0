<?php

declare(strict_types=1);

namespace App\Modules\Casos\Infrastructure\Providers;

use App\Modules\Casos\Application\Listeners\ActivarBanderaCompromisoVigente;
use App\Modules\Casos\Application\Listeners\ActualizarDesnormalizadosDesdeGestion;
use App\Modules\Casos\Application\Listeners\CerrarCasoDesdeGestion;
use App\Modules\Casos\Application\Listeners\RecalcularBanderaCompromisoVigente;
use App\Modules\Casos\Application\UseCases\ReincorporarCuenta;
use App\Modules\Casos\Domain\Contracts\CasoRepository;
use App\Modules\Casos\Domain\Contracts\ReincorporacionDeCuenta;
use App\Modules\Casos\Infrastructure\Http\Livewire\CrearCasoIndividual;
use App\Modules\Casos\Infrastructure\Http\Livewire\EditarCaso;
use App\Modules\Casos\Infrastructure\Http\Livewire\FichaHistorica;
use App\Modules\Casos\Infrastructure\Http\Livewire\ListadoCasos;
use App\Modules\Casos\Infrastructure\Http\Livewire\ListadoHistorico;
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
        $this->app->bind(ReincorporacionDeCuenta::class, ReincorporarCuenta::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(resource_path('views/modules/casos'), 'casos');

        Livewire::component('casos.vista-de-trabajo', VistaDeTrabajo::class);
        Livewire::component('casos.nueva-gestion', NuevaGestion::class);
        Livewire::component('casos.listado-casos', ListadoCasos::class);
        Livewire::component('casos.crear-caso-individual', CrearCasoIndividual::class);
        Livewire::component('casos.editar-caso', EditarCaso::class);
        Livewire::component('casos.listado-historico', ListadoHistorico::class);
        Livewire::component('casos.ficha-historica', FichaHistorica::class);

        Event::listen(GestionRegistrada::class, ActualizarDesnormalizadosDesdeGestion::class);
        Event::listen(GestionRegistrada::class, CerrarCasoDesdeGestion::class);
        Event::listen(CompromisoCreado::class, ActivarBanderaCompromisoVigente::class);
        Event::listen(CompromisoCumplido::class, RecalcularBanderaCompromisoVigente::class);
        Event::listen(CompromisoRoto::class, RecalcularBanderaCompromisoVigente::class);
        Event::listen(CompromisoCancelado::class, RecalcularBanderaCompromisoVigente::class);
    }
}
