<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Infrastructure\Http\Controllers;

use App\Models\User;
use App\Modules\Integracion\Application\Services\ConsultaPreviewPersona;
use App\Modules\Integracion\Application\UseCases\EmitirSanctumTokenDesdeJwt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * La ficha que el wrapper enseña antes de abrir el CRM.
 *
 * Comprueba DOS cosas y no una. Que el usuario tenga acceso al proyecto es la
 * de siempre, y no basta: un asesor que trabaja para dos clientes del BPO la
 * contesta que sí con el token de cualquiera de los dos. La que faltaba es que
 * el proyecto pertenezca al mandante que pidió ESE token, que viaja como
 * habilidad desde que se emite.
 */
final class PreviewPersonaController
{
    public function __invoke(Request $request, ConsultaPreviewPersona $consulta): JsonResponse
    {
        $request->validate([
            'identificacion' => ['required', 'string'],
            'tipo_identificacion_codigo' => ['required', 'string'],
            'proyecto_id' => ['required', 'integer'],
        ]);

        $proyectoId = (int) $request->input('proyecto_id');

        /** @var User $usuario */
        $usuario = $request->user();

        if (! $usuario->tieneAccesoAProyecto($proyectoId)) {
            return response()->json(['message' => 'No tienes acceso a este proyecto.'], 403);
        }

        if (! $this->elTokenAlcanzaEsteProyecto($request, $proyectoId)) {
            return response()->json(['message' => 'Este token no alcanza a ese proyecto.'], 403);
        }

        $preview = $consulta->consultar(
            $proyectoId,
            (string) $request->input('identificacion'),
            (string) $request->input('tipo_identificacion_codigo'),
            $usuario->carterasPermitidasParaPermiso('casos.ver', $proyectoId),
        );

        if ($preview === null) {
            return response()->json(['message' => 'Persona no encontrada en este proyecto.'], 404);
        }

        return response()->json($preview);
    }

    /**
     * Dos condiciones, y las dos son sobre el CLIENTE, no sobre el proyecto.
     *
     * La primera: que el proyecto pedido cuelgue del mandante que emitió este
     * token. Un token sin esa habilidad es anterior al mecanismo y se le niega
     * en vez de dejarlo pasar «por compatibilidad»: duran ocho horas, así que
     * la ventana en la que alguien se lo encuentra es la de un turno y volver a
     * pedirlo es una llamada del wrapper.
     *
     * La segunda: que ese mandante siga siendo cliente. Dar de baja a uno
     * revoca sus tokens desde la pantalla de administración, pero la baja puede
     * llegar por otro camino —un script, la base a mano— y la garantía no puede
     * depender de que alguien pase por un botón. Es una consulta por petición
     * sobre una clave primaria.
     */
    private function elTokenAlcanzaEsteProyecto(Request $request, int $proyectoId): bool
    {
        // El proyecto tiene que existir, estar activo y no estar archivado. Sin
        // esto, un proyecto retirado seguía entregando fichas por la API
        // mientras el CRM ya no lo enseñaba en ninguna pantalla: se cerraron
        // los tres caminos de dentro y quedó abierta la puerta de fuera.
        $proyecto = DB::table('proyectos')
            ->where('id', $proyectoId)
            ->where('activo', true)
            ->whereNull('eliminada_en')
            ->first(['mandante_id']);

        if ($proyecto === null) {
            return false;
        }

        $mandanteId = $proyecto->mandante_id;

        if (! $this->elTokenDeclaraElMandante($request, (int) $mandanteId)) {
            return false;
        }

        return DB::table('mandantes')
            ->where('id', $mandanteId)
            ->where('activo', true)
            ->whereNull('eliminada_en')
            ->exists();
    }

    /**
     * El comodín no cuenta.
     *
     * `tokenCan()` responde que sí a cualquier cosa cuando el token se emitió
     * con `['*']`, que es justo lo que tenían los tokens viejos del wrapper: el
     * comodín contestaría que sí a `mandante:` de cualquier cliente. Por eso se
     * mira la lista de habilidades cuando está —el caso real— y sólo se cae en
     * `tokenCan()` cuando no la hay, que es el token fingido de las pruebas.
     */
    private function elTokenDeclaraElMandante(Request $request, int $mandanteId): bool
    {
        $habilidad = EmitirSanctumTokenDesdeJwt::habilidadDeMandante($mandanteId);
        /** @var User $usuario */
        $usuario = $request->user();
        // Siempre hay token: la ruta va tras `auth:sanctum` con bearer, y para
        // una sesión web Sanctum devuelve un token transitorio, no null.
        $declaradas = $usuario->currentAccessToken()->abilities ?? null;

        if (is_array($declaradas)) {
            return in_array($habilidad, $declaradas, true);
        }

        return $usuario->tokenCan($habilidad);
    }
}
