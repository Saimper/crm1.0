<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Infrastructure\Http\Controllers;

use App\Modules\Integracion\Application\UseCases\ListarProyectosMandante;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * F37: GET /api/integracion/proyectos
 *
 * Devuelve los proyectos del mandante autenticado vía HMAC. Usado por el
 * wrapper para poblar dropdowns de mapeo "campaña → crm_proyecto_id".
 *
 * El mandante_id se obtiene del request attribute inyectado por
 * VerificarFirmaHmacMandante; nunca del body o query.
 */
final class ProyectosMandanteController
{
    public function __construct(
        private readonly ListarProyectosMandante $listar,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $mandanteId = (int) $request->attributes->get('mandante_id');

        return response()->json([
            'mandante_id' => $mandanteId,
            'proyectos' => $this->listar->execute($mandanteId),
        ]);
    }
}
