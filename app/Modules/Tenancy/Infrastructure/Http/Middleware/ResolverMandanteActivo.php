<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Http\Middleware;

use App\Modules\Tenancy\Application\Services\ResolutorMandanteActivo as Resolutor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Publica `tenancy.mandante_activo` en el contenedor: el cliente dentro del cual
 * transcurre esta petición.
 *
 * Es el hermano de ResolverProyectoActivo, un piso más arriba. Hasta ahora no
 * existía, y por eso todo /admin corría sin tenant.
 *
 * De dónde sale el mandante, en este orden y sin más fuentes:
 *
 *  1. Del PROYECTO activo, si ya lo resolvió el middleware de proyecto. En las
 *     rutas operativas el mandante se DERIVA; nunca se acepta por separado.
 *     Aceptar los dos y confiar en que casen es como se cruzan los tenants.
 *  2. Del mandante elegido en sesión, revalidado en CADA petición contra los
 *     mandantes que el usuario puede ver. La sesión propone, el permiso dispone:
 *     si al usuario le revocan el acceso, su sesión deja de valer al instante.
 *  3. Del único que el usuario alcanza, si solo alcanza uno. No tiene sentido
 *     pedirle que elija a un admin de un solo cliente.
 *
 * Y de ningún sitio más. En particular NUNCA del `Referer`, que lo controla
 * quien hace la petición.
 *
 * Si no hay forma de resolverlo, no se inventa uno: se manda a elegir. Un
 * contexto de tenant adivinado es peor que no tenerlo.
 */
final class ResolverMandanteActivo
{
    public const CLAVE_SESION = 'tenancy.mandante_activo_id';

    public function __construct(private readonly Resolutor $resolutor) {}

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario === null) {
            abort(401);
        }

        // Livewire re-aplica este middleware sobre una petición reconstruida a
        // partir del snapshot firmado (ver addPersistentMiddleware en
        // TenancyServiceProvider). Ahí no se puede redirigir: el navegador
        // espera JSON. Pero tampoco se deja pasar sin contexto — que es
        // exactamente el patrón por el que AdminUsuarios acabó filtrando en la
        // lectura y no en la escritura. Se corta con 409 y el front lo ve.
        $esLivewire = $request->hasHeader('X-Livewire') || $request->is('livewire/*');

        // 1 · Derivado del proyecto activo: es la fuente más fiable porque ya
        //     pasó por la validación de acceso de ResolverProyectoActivo.
        if (app()->bound('tenancy.proyecto_activo')) {
            $proyecto = app('tenancy.proyecto_activo');
            $mandanteId = (int) (is_object($proyecto) ? $proyecto->mandante_id : $this->resolutor->delProyecto((int) $proyecto));

            $mandante = $this->resolutor->resolver($usuario, $mandanteId);

            if ($mandante === null) {
                abort(403, 'No tienes acceso a este cliente.');
            }


            return $this->continuar($request, $next, $mandante);
        }

        // 2 · El elegido en sesión, revalidado contra los permisos de ahora.
        $deSesion = $request->session()->get(self::CLAVE_SESION);
        $mandante = $this->resolutor->resolver($usuario, $deSesion === null ? null : (int) $deSesion);

        // 3 · El único que alcanza.
        if ($mandante === null) {
            $mandante = $this->resolutor->resolver($usuario, $this->resolutor->unicoPermitido($usuario));
        }

        if ($mandante === null) {
            $request->session()->forget(self::CLAVE_SESION);

            return $this->pedirQueElija($request, $esLivewire);
        }

        return $this->continuar($request, $next, $mandante);
    }

    private function continuar(Request $request, Closure $next, object $mandante): Response
    {
        app()->instance('tenancy.mandante_activo', $mandante);
        $request->session()->put(self::CLAVE_SESION, (int) $mandante->id);

        return $next($request);
    }

    private function pedirQueElija(Request $request, bool $esLivewire): Response
    {
        if ($esLivewire || $request->expectsJson()) {
            abort(409, 'Sin cliente activo: elige uno antes de continuar.');
        }

        return redirect()->route('admin.mandante-activo');
    }
}
