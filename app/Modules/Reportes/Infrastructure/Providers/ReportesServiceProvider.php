<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Infrastructure\Providers;

use App\Modules\Reportes\Domain\Constructor\Contracts\RepositorioDefinicionReporte;
use App\Modules\Reportes\Infrastructure\Http\Livewire\ConstructorReporte;
use App\Modules\Reportes\Infrastructure\Http\Livewire\DashboardAnalitico;
use App\Modules\Reportes\Infrastructure\Http\Livewire\DashboardOperativo;
use App\Modules\Reportes\Infrastructure\Http\Livewire\ListadoReportesCustom;
use App\Modules\Reportes\Infrastructure\Http\Livewire\ReporteEquipos;
use App\Modules\Reportes\Infrastructure\Persistence\Repositories\RepositorioDefinicionReporteEloquent;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class ReportesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(RepositorioDefinicionReporte::class, RepositorioDefinicionReporteEloquent::class);
    }

    public function boot(): void
    {
        View::addNamespace('reportes', resource_path('views/modules/reportes'));
        Livewire::component('reportes.dashboard-operativo', DashboardOperativo::class);
        Livewire::component('reportes.dashboard-analitico', DashboardAnalitico::class);
        Livewire::component('reportes.reporte-equipos', ReporteEquipos::class);
        Livewire::component('reportes.constructor-reporte', ConstructorReporte::class);
        Livewire::component('reportes.listado-reportes-custom', ListadoReportesCustom::class);
    }
}
