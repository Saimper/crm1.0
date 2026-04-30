<?php

declare(strict_types=1);

namespace App\Modules\Compromisos\Infrastructure\Providers;

use App\Modules\Compromisos\Domain\Contracts\CompromisoRepository;
use App\Modules\Compromisos\Infrastructure\Http\Livewire\ListadoCompromisos;
use App\Modules\Compromisos\Infrastructure\Persistence\Repositories\EloquentCompromisoRepository;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class CompromisosServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CompromisoRepository::class, EloquentCompromisoRepository::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(resource_path('views/modules/compromisos'), 'compromisos');

        Livewire::component('compromisos.listado-compromisos', ListadoCompromisos::class);
    }
}
