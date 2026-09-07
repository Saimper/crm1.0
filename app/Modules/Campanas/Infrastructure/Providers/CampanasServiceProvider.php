<?php

declare(strict_types=1);

namespace App\Modules\Campanas\Infrastructure\Providers;

use App\Modules\Campanas\Domain\Contracts\CampanaRepository;
use App\Modules\Campanas\Infrastructure\Http\Livewire\CampanasProyecto;
use App\Modules\Campanas\Infrastructure\Persistence\Repositories\EloquentCampanaRepository;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class CampanasServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CampanaRepository::class, EloquentCampanaRepository::class);

        $this->loadViewsFrom(resource_path('views/modules/campanas'), 'campanas');

        Livewire::component('campanas.campanas-proyecto', CampanasProyecto::class);
    }

    public function boot(): void {}
}
