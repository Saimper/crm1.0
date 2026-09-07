<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Support;

use App\Modules\Tenancy\Domain\Exceptions\ConsultaSinContextoDeTenant;
use Illuminate\Support\Facades\Log;

/**
 * Qué hacer cuando una consulta con scope de tenant no encuentra contexto.
 *
 * Vive aparte de los dos traits para que ambos se comporten igual: proyecto y
 * mandante tienen que cerrarse a la vez o el aislamiento queda a medias.
 */
final class GuardiaDeContexto
{
    /**
     * @param  string  $modelo  clase del modelo consultado
     * @param  string  $tipo    'proyecto' o 'mandante'
     *
     * @throws ConsultaSinContextoDeTenant en modo estricto
     */
    public static function sinContexto(string $modelo, string $tipo): void
    {
        if ((bool) config('tenancy.scope_estricto', false)) {
            throw ConsultaSinContextoDeTenant::paraModelo($modelo, $tipo);
        }

        if (! (bool) config('tenancy.avisar_sin_contexto', false)) {
            return;
        }

        // El punto del código que lanzó la consulta, saltándose el propio scope,
        // Eloquent y el framework: lo que interesa es el fichero de la aplicación.
        $origen = null;

        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 30) as $paso) {
            $fichero = $paso['file'] ?? '';

            if ($fichero === '' || str_contains($fichero, '/vendor/')) {
                continue;
            }

            if (str_contains($fichero, 'Tenancy/Infrastructure/Support/')) {
                continue;
            }

            $origen = $fichero.':'.($paso['line'] ?? 0);
            break;
        }

        Log::warning('consulta sin contexto de tenant', [
            'modelo' => $modelo,
            'tipo' => $tipo,
            'origen' => $origen,
        ]);
    }
}
