<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Aplica el idioma preferido del usuario autenticado (columna users.locale).
     * Si no hay preferencia válida, mantiene el locale por defecto de la app.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $soportados = array_keys((array) config('locales.supported', []));
        $preferido = $request->user()?->locale;

        if (is_string($preferido) && in_array($preferido, $soportados, true)) {
            App::setLocale($preferido);
        }

        return $next($request);
    }
}
