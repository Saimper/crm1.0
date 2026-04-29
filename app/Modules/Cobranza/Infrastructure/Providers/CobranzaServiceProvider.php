<?php

declare(strict_types=1);

namespace App\Modules\Cobranza\Infrastructure\Providers;

use App\Modules\Cobranza\Application\Listeners\CrearPromesaDesdeGestion;
use App\Modules\Cobranza\Domain\Contracts\CasoCobranzaRepository;
use App\Modules\Cobranza\Domain\Contracts\CompromisoPromesaPagoRepository;
use App\Modules\Cobranza\Domain\Contracts\TipoPagoRepository;
use App\Modules\Cobranza\Domain\Contracts\TramoMoraRepository;
use App\Modules\Cobranza\Infrastructure\Http\Livewire\AdminTiposPago;
use App\Modules\Cobranza\Infrastructure\Http\Livewire\AdminTramosMora;
use App\Modules\Cobranza\Infrastructure\Http\Livewire\ResolverPromesa;
use App\Modules\Cobranza\Infrastructure\Persistence\Repositories\EloquentCasoCobranzaRepository;
use App\Modules\Cobranza\Infrastructure\Persistence\Repositories\EloquentCompromisoPromesaPagoRepository;
use App\Modules\Cobranza\Infrastructure\Persistence\Repositories\EloquentTipoPagoRepository;
use App\Modules\Cobranza\Infrastructure\Persistence\Repositories\EloquentTramoMoraRepository;
use App\Modules\Gestiones\Domain\Events\GestionRegistrada;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class CobranzaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CasoCobranzaRepository::class, EloquentCasoCobranzaRepository::class);
        $this->app->bind(CompromisoPromesaPagoRepository::class, EloquentCompromisoPromesaPagoRepository::class);
        $this->app->bind(TramoMoraRepository::class, EloquentTramoMoraRepository::class);
        $this->app->bind(TipoPagoRepository::class, EloquentTipoPagoRepository::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(resource_path('views/modules/cobranza'), 'cobranza');

        Livewire::component('cobranza.resolver-promesa', ResolverPromesa::class);
        Livewire::component('cobranza.admin-tramos-mora', AdminTramosMora::class);
        Livewire::component('cobranza.admin-tipos-pago',  AdminTiposPago::class);

        Event::listen(GestionRegistrada::class, CrearPromesaDesdeGestion::class);
    }
}
