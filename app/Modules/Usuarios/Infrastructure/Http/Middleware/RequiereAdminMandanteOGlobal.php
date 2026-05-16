<?php

declare(strict_types=1);

namespace App\Modules\Usuarios\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * F39: rutas admin compartidas entre ADMIN_GLOBAL y ADMIN_MANDANTE.
 *
 * El scoping (qué proyectos/usuarios ve cada uno) lo aplica cada Livewire
 * inspeccionando $usuario->esAdminGlobal() vs $usuario->mandantesAdministrados().
 */
final class RequiereAdminMandanteOGlobal
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();
        abort_unless($usuario !== null, 401);

        $esAdminGlobal = $usuario->esAdminGlobal();
        $esAdminMandante = $usuario->mandantesAdministrados() !== [];

        abort_unless(
            $esAdminGlobal || $esAdminMandante,
            403,
            'Esta ruta requiere rol ADMIN_GLOBAL o ADMIN_MANDANTE.',
        );

        return $next($request);
    }
}
