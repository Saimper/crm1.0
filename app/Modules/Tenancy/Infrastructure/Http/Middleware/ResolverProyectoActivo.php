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
            $this->cortar($request, 404, 'Ruta sin proyecto activo.');
        }

        /** @var ProyectoModel|null $proyecto */
        $proyecto = ProyectoModel::query()
            ->whereKey($proyectoId)
            ->whereNull('eliminada_en')
            ->where('activo', true)
            ->first();

        if ($proyecto === null) {
            $this->cortar($request, 404, 'Proyecto no encontrado o inactivo.');
        }

        $usuario = $request->user();
        if ($usuario === null) {
            $this->cortar($request, 401, 'No autenticado.');
        }

        if (! $usuario->tieneAccesoAProyecto((int) $proyecto->id)) {
            $this->cortar($request, 403, 'No tienes acceso a este proyecto.');
        }

        app()->instance('tenancy.proyecto_activo', $proyecto);

        return $next($request);
    }

    /**
     * El proyecto sale SIEMPRE del parámetro de ruta, y de ningún otro sitio.
     *
     * Antes había un respaldo que lo sacaba del header `Referer` cuando la
     * petición venía de /livewire/update. Era innecesario y peligroso a la vez:
     *
     * - Innecesario, porque Livewire re-aplica los middleware persistentes sobre
     *   una petición reconstruida cuyo REQUEST_URI es el path ORIGINAL guardado
     *   en el snapshot (Mechanisms/PersistentMiddleware::makeFakeRequest), con la
     *   ruta ya matcheada y sus parámetros bindeados. `route('proyecto_id')`
     *   resuelve ahí perfectamente, y ese path va firmado en el checksum.
     * - Peligroso, porque el `Referer` lo pone quien hace la petición. El proyecto
     *   activo gobierna el global scope de 36 modelos: dejar que el cliente lo
     *   elija es dejarle elegir qué datos ve.
     */
    private function resolverProyectoId(Request $request): ?int
    {
        $fromRoute = $request->route('proyecto_id');

        return $fromRoute === null ? null : (int) $fromRoute;
    }

    /**
     * Antes, cuando algo fallaba en una petición de Livewire, se dejaba pasar sin
     * proyecto activo. Sin binding el global scope no filtra nada (fallo
     * abierto), así que el camino de error era también el camino sin aislamiento:
     * bastaba con que la resolución fallara para consultar sobre todos los
     * proyectos. Ahora se corta siempre; lo único que cambia en Livewire es que
     * no se puede redirigir, así que el código de estado viaja como tal.
     */
    private function cortar(Request $request, int $codigo, string $mensaje): never
    {
        abort($codigo, $mensaje);
    }

}
