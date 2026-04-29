<?php

declare(strict_types=1);

namespace App\Modules\Usuarios\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequiereAdminGlobal
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();
        abort_unless($usuario !== null, 401);
        abort_unless(
            $usuario->esAdminGlobal(),
            403,
            'Esta ruta requiere rol ADMIN_GLOBAL.',
        );

        return $next($request);
    }
}
