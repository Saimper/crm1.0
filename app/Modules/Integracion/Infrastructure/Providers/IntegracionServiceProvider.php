<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Infrastructure\Providers;

use App\Modules\Integracion\Application\UseCases\ConsumirTokenSso;
use App\Modules\Integracion\Application\UseCases\EmitirTokenSso;
use App\Modules\Integracion\Domain\Contracts\RepositorioTokenSso;
use App\Modules\Integracion\Infrastructure\Http\Controllers\PreviewPersonaController;
use App\Modules\Integracion\Infrastructure\Http\Controllers\SsoHandshakeController;
use App\Modules\Integracion\Infrastructure\Http\Controllers\SsoLogoutController;
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

        $this->registrarRutasApi();
        $this->registrarRutasWeb();
    }

    private function registrarRutasApi(): void
    {
        Route::middleware(['api'])
            ->prefix('api')
            ->group(function (): void {
                Route::post('/auth/sso-handshake', [SsoHandshakeController::class, 'emitir'])
                    ->middleware('throttle:10,1')
                    ->name('api.sso.handshake');

                Route::middleware('auth:sanctum')->group(function (): void {
                    Route::post('/auth/logout', SsoLogoutController::class)
                        ->name('api.sso.logout');

                    Route::get('/integracion/persona', PreviewPersonaController::class)
                        ->middleware('throttle:60,1')
                        ->name('api.integracion.persona');
                });
            });
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
