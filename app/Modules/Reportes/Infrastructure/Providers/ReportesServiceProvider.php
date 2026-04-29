<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Infrastructure\Providers;

use App\Modules\Reportes\Infrastructure\Http\Livewire\DashboardAnalitico;
use App\Modules\Reportes\Infrastructure\Http\Livewire\DashboardOperativo;
use App\Modules\Reportes\Infrastructure\Http\Livewire\ReporteEquipos;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class ReportesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        View::addNamespace('reportes', resource_path('views/modules/reportes'));
        Livewire::component('reportes.dashboard-operativo', DashboardOperativo::class);
        Livewire::component('reportes.dashboard-analitico', DashboardAnalitico::class);
        Livewire::component('reportes.reporte-equipos', ReporteEquipos::class);
    }
}
