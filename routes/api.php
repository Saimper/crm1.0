<?php

declare(strict_types=1);

use App\Modules\Integracion\Infrastructure\Http\Controllers\PreviewPersonaController;
use App\Modules\Integracion\Infrastructure\Http\Controllers\SsoHandshakeController;
use App\Modules\Integracion\Infrastructure\Http\Controllers\SsoLogoutController;
use Illuminate\Support\Facades\Route;

/*
 * Rutas API del módulo Integración (F28). Originalmente registradas
 * dinámicamente en IntegracionServiceProvider; migradas aquí en F34C
 * para que `php artisan route:list --domain=api` las muestre y
 * bootstrap/app.php las descubra explícitamente.
 */

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
