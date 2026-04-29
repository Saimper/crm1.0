<?php

declare(strict_types=1);

namespace App\Modules\Venta\Infrastructure\Providers;

use App\Modules\Gestiones\Domain\Events\GestionRegistrada;
use App\Modules\Venta\Application\Listeners\CrearCierreDesdeGestion;
use App\Modules\Venta\Domain\Contracts\CasoLeadVentaRepository;
use App\Modules\Venta\Domain\Contracts\CompromisoCierreVentaRepository;
use App\Modules\Venta\Infrastructure\Http\Livewire\AdminEtapasEmbudo;
use App\Modules\Venta\Infrastructure\Http\Livewire\AdminProductosVenta;
use App\Modules\Venta\Infrastructure\Http\Livewire\ResolverCierre;
use App\Modules\Venta\Infrastructure\Persistence\Repositories\EloquentCasoLeadVentaRepository;
use App\Modules\Venta\Infrastructure\Persistence\Repositories\EloquentCompromisoCierreVentaRepository;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class VentaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CasoLeadVentaRepository::class, EloquentCasoLeadVentaRepository::class);
        $this->app->bind(CompromisoCierreVentaRepository::class, EloquentCompromisoCierreVentaRepository::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(resource_path('views/modules/venta'), 'venta');

        Livewire::component('venta.resolver-cierre', ResolverCierre::class);
        Livewire::component('venta.admin-productos-venta', AdminProductosVenta::class);
        Livewire::component('venta.admin-etapas-embudo',   AdminEtapasEmbudo::class);

        Event::listen(GestionRegistrada::class, CrearCierreDesdeGestion::class);
    }
}
