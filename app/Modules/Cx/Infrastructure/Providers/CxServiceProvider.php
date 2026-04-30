<?php

declare(strict_types=1);

namespace App\Modules\Cx\Infrastructure\Providers;

use App\Modules\Cx\Application\Listeners\CrearResolucionDesdeGestion;
use App\Modules\Cx\Domain\Contracts\CasoTicketCxRepository;
use App\Modules\Cx\Domain\Contracts\CompromisoResolucionTicketRepository;
use App\Modules\Cx\Domain\Contracts\NivelEscalamientoRepository;
use App\Modules\Cx\Infrastructure\Http\Livewire\AdminCategoriasTicket;
use App\Modules\Cx\Infrastructure\Http\Livewire\AdminNivelesEscalamiento;
use App\Modules\Cx\Infrastructure\Http\Livewire\AdminNivelesSla;
use App\Modules\Cx\Infrastructure\Http\Livewire\AdminPrioridadesTicket;
use App\Modules\Cx\Infrastructure\Http\Livewire\ResolverResolucion;
use App\Modules\Cx\Infrastructure\Persistence\Repositories\EloquentCasoTicketCxRepository;
use App\Modules\Cx\Infrastructure\Persistence\Repositories\EloquentCompromisoResolucionTicketRepository;
use App\Modules\Cx\Infrastructure\Persistence\Repositories\EloquentNivelEscalamientoRepository;
use App\Modules\Gestiones\Domain\Events\GestionRegistrada;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class CxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CasoTicketCxRepository::class, EloquentCasoTicketCxRepository::class);
        $this->app->bind(CompromisoResolucionTicketRepository::class, EloquentCompromisoResolucionTicketRepository::class);
        $this->app->bind(NivelEscalamientoRepository::class, EloquentNivelEscalamientoRepository::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(resource_path('views/modules/cx'), 'cx');

        Livewire::component('cx.resolver-resolucion', ResolverResolucion::class);
        Livewire::component('cx.admin-categorias-ticket', AdminCategoriasTicket::class);
        Livewire::component('cx.admin-prioridades-ticket', AdminPrioridadesTicket::class);
        Livewire::component('cx.admin-niveles-sla', AdminNivelesSla::class);
        Livewire::component('cx.admin-niveles-escalamiento', AdminNivelesEscalamiento::class);

        Event::listen(GestionRegistrada::class, CrearResolucionDesdeGestion::class);
    }
}
