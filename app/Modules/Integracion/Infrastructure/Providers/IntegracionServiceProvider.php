<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Infrastructure\Providers;

use App\Modules\Integracion\Application\UseCases\ConsumirTokenSso;
use App\Modules\Integracion\Application\UseCases\EmitirTokenSso;
use App\Modules\Integracion\Domain\Contracts\RepositorioTokenSso;
use App\Modules\Integracion\Infrastructure\Http\Controllers\SsoHandshakeController;
use App\Modules\Integracion\Infrastructure\Http\Middleware\CspFrameAncestors;
use App\Modules\Integracion\Infrastructure\Persistence\Repositories\RepositorioTokenSsoEloquent;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class IntegracionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(RepositorioTokenSso::class, RepositorioTokenSsoEloquent::class);

        $this->app->when(SsoHandshakeController::class)
            ->needs(EmitirTokenSso::class)
            ->give(EmitirTokenSso::class);

        $this->app->when(SsoHandshakeController::class)
            ->needs(ConsumirTokenSso::class)
            ->give(ConsumirTokenSso::class);
    }

    public function boot(Router $router): void
    {
        $router->aliasMiddleware('csp.frame-ancestors', CspFrameAncestors::class);

        // F34C: las rutas API se cargan desde routes/api.php (declaradas en
        // bootstrap/app.php). Aquí solo quedan las web (handshake browser).
        $this->registrarRutasWeb();
    }

    private function registrarRutasWeb(): void
    {
        Route::middleware(['web', 'csp.frame-ancestors'])
            ->group(function (): void {
                Route::get('/integracion/handshake', [SsoHandshakeController::class, 'consumir'])
                    ->name('integracion.handshake.consumir');
            });
    }
}
