<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Http\Middleware;

use App\Modules\Tenancy\Infrastructure\Persistence\Models\ProyectoModel;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resuelve el proyecto activo desde {proyecto_id} de la URL y valida:
 *  - que el proyecto existe y está activo (soft delete nulo, flag activo=true),
 *  - que el usuario autenticado tiene acceso (ADMIN_GLOBAL o asignación en usuario_proyecto_rol).
 *
 * Publica el modelo en el container como `tenancy.proyecto_activo` para que lo usen
 * el Global Scope de trait `PerteneceAProyecto` y los Gates contextualizados.
 *
 * Persistent para Livewire (registrado en TenancyServiceProvider): en requests a
 * /livewire/update extraemos el proyecto_id del Referer, ya que esas rutas no tienen
 * el parámetro `{proyecto_id}` en su definición.
 */
final class ResolverProyectoActivo
{
    public function handle(Request $request, Closure $next): Response
    {
        $proyectoId = $this->resolverProyectoId($request);

        if ($proyectoId === null) {
            if ($this->esRequestLivewire($request)) {
                return $next($request);
            }
            abort(404, 'Ruta sin proyecto activo.');
        }

        /** @var ProyectoModel|null $proyecto */
        $proyecto = ProyectoModel::query()
            ->whereKey($proyectoId)
            ->whereNull('eliminada_en')
            ->where('activo', true)
            ->first();

        if ($proyecto === null) {
            if ($this->esRequestLivewire($request)) {
                return $next($request);
            }
            abort(404, 'Proyecto no encontrado o inactivo.');
        }

        $usuario = $request->user();
        if ($usuario === null) {
            if ($this->esRequestLivewire($request)) {
                return $next($request);
            }
            abort(401);
        }

        if (! $usuario->tieneAccesoAProyecto((int) $proyecto->id)) {
            if ($this->esRequestLivewire($request)) {
                return $next($request);
            }
            abort(403, 'No tienes acceso a este proyecto.');
        }

        app()->instance('tenancy.proyecto_activo', $proyecto);

        return $next($request);
    }

    private function resolverProyectoId(Request $request): ?int
    {
        $fromRoute = $request->route('proyecto_id');
        if ($fromRoute !== null) {
            return (int) $fromRoute;
        }

        // Livewire POST /livewire/update no tiene el parámetro en la ruta; lo extraemos del Referer.
        $referer = (string) $request->headers->get('referer', '');
        if ($referer !== '') {
            $path = (string) parse_url($referer, PHP_URL_PATH);
            if (preg_match('#/proyectos/(\d+)#', $path, $m)) {
                return (int) $m[1];
            }
        }

        return null;
    }

    private function esRequestLivewire(Request $request): bool
    {
        return $request->is('livewire/*') || $request->hasHeader('X-Livewire');
    }
}
