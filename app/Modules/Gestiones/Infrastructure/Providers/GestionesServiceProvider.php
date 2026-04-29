<?php

declare(strict_types=1);

namespace App\Modules\Gestiones\Infrastructure\Providers;

use App\Modules\Gestiones\Domain\Contracts\ConsultaResultado;
use App\Modules\Gestiones\Domain\Contracts\GestionRepository;
use App\Modules\Gestiones\Infrastructure\Adapters\ConsultaResultadoEloquent;
use App\Modules\Gestiones\Infrastructure\Persistence\Repositories\EloquentGestionRepository;
use Illuminate\Support\ServiceProvider;

final class GestionesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(GestionRepository::class, EloquentGestionRepository::class);
        $this->app->bind(ConsultaResultado::class, ConsultaResultadoEloquent::class);
    }

    public function boot(): void
    {
    }
}
