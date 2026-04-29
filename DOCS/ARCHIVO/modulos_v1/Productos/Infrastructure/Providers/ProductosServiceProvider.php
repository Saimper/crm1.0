<?php

declare(strict_types=1);

namespace App\Modules\Productos\Infrastructure\Providers;

use App\Modules\Gestiones\Domain\Events\GestionRegistrada;
use App\Modules\Productos\Application\Listeners\ActualizarBanderaPromesaVigente;
use App\Modules\Productos\Application\Listeners\ActualizarDesnormalizadosDesdeGestion;
use App\Modules\Productos\Application\Listeners\RecalcularBanderaPromesaVigente;
use App\Modules\Promesas\Domain\Events\PromesaCancelada;
use App\Modules\Promesas\Domain\Events\PromesaCreada;
use App\Modules\Promesas\Domain\Events\PromesaCumplida;
use App\Modules\Promesas\Domain\Events\PromesaRota;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class ProductosServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        Event::listen(GestionRegistrada::class, ActualizarDesnormalizadosDesdeGestion::class);
        Event::listen(PromesaCreada::class,     ActualizarBanderaPromesaVigente::class);
        Event::listen(PromesaCumplida::class,   RecalcularBanderaPromesaVigente::class);
        Event::listen(PromesaRota::class,       RecalcularBanderaPromesaVigente::class);
        Event::listen(PromesaCancelada::class,  RecalcularBanderaPromesaVigente::class);
    }
}
