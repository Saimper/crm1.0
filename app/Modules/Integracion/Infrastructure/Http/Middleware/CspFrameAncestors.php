<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CspFrameAncestors
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $wrapperDomain = (string) config('integracion.wrapper_domain', '');

        if ($wrapperDomain === '') {
            return $response;
        }

        $response->headers->set(
            'Content-Security-Policy',
            "frame-ancestors 'self' {$wrapperDomain}"
        );
        $response->headers->remove('X-Frame-Options');

        return $response;
    }
}
