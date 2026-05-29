<?php

declare(strict_types=1);

use App\Modules\Integracion\Infrastructure\Http\Controllers\CamposDisponiblesController;
use App\Modules\Integracion\Infrastructure\Http\Controllers\EmitirSanctumTokenController;
use App\Modules\Integracion\Infrastructure\Http\Controllers\PreviewPersonaController;
use App\Modules\Integracion\Infrastructure\Http\Controllers\ProyectosMandanteController;
use App\Modules\Integracion\Infrastructure\Http\Controllers\SsoLogoutController;
use Illuminate\Support\Facades\Route;

/*
 * Rutas API del módulo Integración. Migradas a routes/api.php en F34C.
 * Tras F35 el endpoint POST /auth/sso-handshake desaparece: el handshake
 * lo inicia el wrapper firmando un JWT HS256 y lo consume el browser
 * vía GET /integracion/handshake (registrada en el provider del módulo).
 *
 * El endpoint POST /integracion/sanctum-token es server-to-server: el wrapper
 * firma un JWT idéntico al del handshake y a cambio recibe un Sanctum PAT
 * para llamar APIs (logout, preview persona, etc.) en nombre del user JIT.
 */

Route::post('/integracion/sanctum-token', EmitirSanctumTokenController::class)
    ->middleware('throttle:10,1')
    ->name('api.integracion.sanctum-token');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/auth/logout', SsoLogoutController::class)
        ->name('api.sso.logout');

    Route::get('/integracion/persona', PreviewPersonaController::class)
        ->middleware('throttle:60,1')
        ->name('api.integracion.persona');
});

// F37: endpoint server-to-server autenticado por HMAC con sso_secret del
// mandante. Usado por el wrapper para listar proyectos de su mandante y
// poblar el dropdown de mapeo "campaña → crm_proyecto_id".
Route::middleware(['hmac.mandante', 'throttle:60,1'])->group(function (): void {
    Route::get('/integracion/proyectos', ProyectosMandanteController::class)
        ->name('api.integracion.proyectos');

    // Campos del CRM mapeables a campos del lead de ViciDial (UI de mapeo del wrapper).
    Route::get('/integracion/campos', CamposDisponiblesController::class)
        ->name('api.integracion.campos');
});
