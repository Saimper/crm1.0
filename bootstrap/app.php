<?php

use App\Exceptions\PayloadLivewireInvalido;
use App\Http\Middleware\RechazarUsuarioDesactivado;
use App\Http\Middleware\SetLocale;
use App\Modules\Integracion\Infrastructure\Http\Middleware\CspFrameAncestors;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetLocale::class,
            // Va en el grupo web y no en las rutas: dar de baja a alguien tiene
            // que echarlo de donde esté, no sólo impedirle volver a entrar.
            RechazarUsuarioDesactivado::class,
            // El CRM vive dentro del iframe del wrapper (screen-pop): todas las
            // páginas, no sólo el 302 del handshake, declaran quién puede
            // embeberlas. Sin WRAPPER_DOMAIN el middleware no emite nada.
            CspFrameAncestors::class,
        ]);

        // Sanctum trae las clases pero no registra los alias: sin esto,
        // `ability:` en una ruta revienta al resolverse, no al arrancar.
        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Payloads de Livewire que no corresponden a ningún componente/acción real
        // (escáneres, snapshots manipulados, clientes v2): 4xx silencioso, no 500 + stack.
        $exceptions->report(fn (Throwable $e): ?bool => PayloadLivewireInvalido::aplica($e) ? false : null);
        $exceptions->render(fn (Throwable $e) => PayloadLivewireInvalido::aplica($e) ? PayloadLivewireInvalido::responder($e) : null);
    })->create();
