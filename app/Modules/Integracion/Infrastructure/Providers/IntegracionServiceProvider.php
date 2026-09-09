<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Infrastructure\Providers;

use App\Modules\Integracion\Application\Console\Commands\PurgarSsoTokensConsumidosCommand;
use App\Modules\Integracion\Application\Services\EmisorWritebackFichaPorWebhook;
use App\Modules\Integracion\Domain\Contracts\EmisorWritebackFicha;
use App\Modules\Integracion\Domain\Contracts\RepositorioTokensConsumidos;
use App\Modules\Integracion\Infrastructure\Http\Controllers\SsoHandshakeController;
use App\Modules\Integracion\Infrastructure\Http\Livewire\AdminSsoSecrets;
use App\Modules\Integracion\Infrastructure\Http\Middleware\CspFrameAncestors;
use App\Modules\Integracion\Infrastructure\Http\Middleware\VerificarFirmaHmacMandante;
use App\Modules\Integracion\Infrastructure\Persistence\Repositories\RepositorioTokensConsumidosEloquent;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class IntegracionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(RepositorioTokensConsumidos::class, RepositorioTokensConsumidosEloquent::class);
        $this->app->bind(EmisorWritebackFicha::class, EmisorWritebackFichaPorWebhook::class);
    }

    public function boot(Router $router): void
    {
        $router->aliasMiddleware('csp.frame-ancestors', CspFrameAncestors::class);
        $router->aliasMiddleware('hmac.mandante', VerificarFirmaHmacMandante::class);

        $this->loadViewsFrom(resource_path('views/modules/integracion'), 'integracion');

        $this->registrarLimiteHandshake();

        // F34C: las rutas API se cargan desde routes/api.php (declaradas en
        // bootstrap/app.php). Aquí solo quedan las web (handshake browser).
        $this->registrarRutasWeb();

        Livewire::component('integracion.admin-sso-secrets', AdminSsoSecrets::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                PurgarSsoTokensConsumidosCommand::class,
            ]);
        }
    }

    /**
     * Con el screen-pop hay un handshake por llamada, y los agentes de un call
     * center salen a internet por la misma IP. El primer handshake de cada
     * sesión no trae usuario y se cuenta por IP; los siguientes viajan con la
     * cookie del iframe y se cuentan por usuario, que es lo que de verdad
     * escala con el volumen de llamadas.
     */
    private function registrarLimiteHandshake(): void
    {
        RateLimiter::for('sso-handshake', static function (Request $request): Limit {
            $usuario = $request->user();

            return $usuario !== null
                ? Limit::perMinute(120)->by('u:'.$usuario->getAuthIdentifier())
                : Limit::perMinute(60)->by('ip:'.$request->ip());
        });
    }

    private function registrarRutasWeb(): void
    {
        Route::middleware(['web', 'csp.frame-ancestors', 'throttle:sso-handshake'])
            ->group(function (): void {
                Route::get('/integracion/handshake', [SsoHandshakeController::class, 'consumir'])
                    ->name('integracion.handshake.consumir');
            });
    }
}
