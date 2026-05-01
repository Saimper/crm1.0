<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Infrastructure\Providers;

use App\Modules\Integracion\Application\Console\Commands\PurgarSsoTokensConsumidosCommand;
use App\Modules\Integracion\Domain\Contracts\RepositorioTokensConsumidos;
use App\Modules\Integracion\Infrastructure\Http\Controllers\SsoHandshakeController;
use App\Modules\Integracion\Infrastructure\Http\Livewire\AdminSsoSecrets;
use App\Modules\Integracion\Infrastructure\Http\Middleware\CspFrameAncestors;
use App\Modules\Integracion\Infrastructure\Persistence\Repositories\RepositorioTokensConsumidosEloquent;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class IntegracionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(RepositorioTokensConsumidos::class, RepositorioTokensConsumidosEloquent::class);
    }

    public function boot(Router $router): void
    {
        $router->aliasMiddleware('csp.frame-ancestors', CspFrameAncestors::class);

        $this->loadViewsFrom(resource_path('views/modules/integracion'), 'integracion');

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

    private function registrarRutasWeb(): void
    {
        Route::middleware(['web', 'csp.frame-ancestors', 'throttle:30,1'])
            ->group(function (): void {
                Route::get('/integracion/handshake', [SsoHandshakeController::class, 'consumir'])
                    ->name('integracion.handshake.consumir');
            });
    }
}
