<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cierra la sesión de un usuario que ha sido desactivado.
 *
 * Comprobarlo sólo al entrar no basta: `SESSION_LIFETIME` son ocho horas, así
 * que dar de baja a alguien a media mañana lo dejaba trabajando el resto del
 * turno. Y `tieneAccesoAProyecto()` no cubre el hueco, porque mira el `activo`
 * del pivote usuario-proyecto, no el de la cuenta: quitarle la marca al usuario
 * no toca esos pivotes y el acceso seguía intacto.
 *
 * Se relee en cada petición a propósito. Es una consulta por request sobre la
 * clave primaria —el coste que ya tiene cualquier `auth()->user()`— y a cambio
 * la baja es inmediata en vez de diferida hasta que caduque la sesión.
 */
final class RechazarUsuarioDesactivado
{
    public function __invoke(Request $request, Closure $next): Response
    {
        return $this->handle($request, $next);
    }

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = Auth::user();

        if ($usuario === null || (bool) $usuario->activo === true) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json(['message' => __('auth.desactivada')], 403);
        }

        return redirect()
            ->route('login')
            ->withErrors(['form.email' => __('auth.desactivada')]);
    }
}
