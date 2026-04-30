<?php

declare(strict_types=1);

namespace App\Modules\Campanas\Infrastructure\Providers;

use App\Modules\Campanas\Domain\Contracts\CampanaRepository;
use App\Modules\Campanas\Infrastructure\Persistence\Repositories\EloquentCampanaRepository;
use Illuminate\Support\ServiceProvider;

final class CampanasServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CampanaRepository::class, EloquentCampanaRepository::class);
    }

    public function boot(): void {}
}
