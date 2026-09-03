<?php

use App\Exceptions\PayloadLivewireInvalido;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Payloads de Livewire que no corresponden a ningún componente/acción real
        // (escáneres, snapshots manipulados, clientes v2): 4xx silencioso, no 500 + stack.
        $exceptions->report(fn (Throwable $e): ?bool => PayloadLivewireInvalido::aplica($e) ? false : null);
        $exceptions->render(fn (Throwable $e) => PayloadLivewireInvalido::aplica($e) ? PayloadLivewireInvalido::responder($e) : null);
    })->create();
