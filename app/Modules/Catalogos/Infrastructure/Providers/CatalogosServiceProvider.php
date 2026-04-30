<?php

declare(strict_types=1);

namespace App\Modules\Catalogos\Infrastructure\Providers;

use App\Modules\Catalogos\Infrastructure\Http\Livewire\AdminCausasGestion;
use App\Modules\Catalogos\Infrastructure\Http\Livewire\AdminEstadosCaso;
use App\Modules\Catalogos\Infrastructure\Http\Livewire\AdminMotivosNoContacto;
use App\Modules\Catalogos\Infrastructure\Http\Livewire\AdminResultadosProyecto;
use App\Modules\Catalogos\Infrastructure\Http\Livewire\AdminTiposGestion;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class CatalogosServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // `ConsultaResultado` lo bindea `GestionesServiceProvider` (adapter v2).
        // El adapter v1 en `App\Modules\Catalogos\Infrastructure\Adapters\ConsultaResultadoEloquent`
        // quedó obsoleto — se archivará. No registrar aquí el binding legacy.
    }

    public function boot(): void
    {
        View::addNamespace('catalogos', resource_path('views/modules/catalogos'));

        Livewire::component('catalogos.admin-resultados', AdminResultadosProyecto::class);
        Livewire::component('catalogos.admin-tipos-gestion', AdminTiposGestion::class);
        Livewire::component('catalogos.admin-causas-gestion', AdminCausasGestion::class);
        Livewire::component('catalogos.admin-motivos-no-contacto', AdminMotivosNoContacto::class);
        Livewire::component('catalogos.admin-estados-caso', AdminEstadosCaso::class);
    }
}
