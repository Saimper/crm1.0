<?php

declare(strict_types=1);

namespace App\Modules\EntidadesConfigurables\Infrastructure\Providers;

use App\Modules\EntidadesConfigurables\Infrastructure\Http\Livewire\AdminEntidadesConfigurables;
use App\Modules\EntidadesConfigurables\Infrastructure\Http\Livewire\GestorRegistrosEntidad;
use App\Modules\EntidadesConfigurables\Infrastructure\Http\Livewire\PanelEntidadesVinculadas;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class EntidadesConfigurablesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        View::addNamespace('entidades', resource_path('views/modules/entidades'));
        Livewire::component('entidades.admin-entidades-configurables', AdminEntidadesConfigurables::class);
        Livewire::component('entidades.gestor-registros-entidad', GestorRegistrosEntidad::class);
        Livewire::component('entidades.panel-vinculadas', PanelEntidadesVinculadas::class);
    }
}
