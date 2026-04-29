<?php

declare(strict_types=1);

namespace App\Modules\CamposPersonalizados\Infrastructure\Providers;

use App\Modules\CamposPersonalizados\Domain\Services\EvaluadorReglas;
use App\Modules\CamposPersonalizados\Infrastructure\Http\Livewire\AdminCamposPersonalizados;
use App\Modules\CamposPersonalizados\Infrastructure\Http\Livewire\FormularioCamposPersonalizados;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class CamposPersonalizadosServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EvaluadorReglas::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(resource_path('views/modules/campos_personalizados'), 'campos_personalizados');

        Livewire::component('campos-personalizados.formulario', FormularioCamposPersonalizados::class);
        Livewire::component('campos-personalizados.admin', AdminCamposPersonalizados::class);
    }
}
