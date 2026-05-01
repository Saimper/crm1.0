<?php

declare(strict_types=1);

use App\Modules\Integracion\Infrastructure\Http\Controllers\PreviewPersonaController;
use App\Modules\Integracion\Infrastructure\Http\Controllers\SsoLogoutController;
use Illuminate\Support\Facades\Route;

/*
 * Rutas API del módulo Integración. Migradas a routes/api.php en F34C.
 * Tras F35 el endpoint POST /auth/sso-handshake desaparece: el handshake
 * lo inicia el wrapper firmando un JWT HS256 y lo consume el browser
 * vía GET /integracion/handshake (registrada en el provider del módulo).
 */

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/auth/logout', SsoLogoutController::class)
        ->name('api.sso.logout');

    Route::get('/integracion/persona', PreviewPersonaController::class)
        ->middleware('throttle:60,1')
        ->name('api.integracion.persona');
});
