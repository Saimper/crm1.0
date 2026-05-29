<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Infrastructure\Http\Controllers;

use App\Modules\Integracion\Application\UseCases\ListarCamposDisponibles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/integracion/campos?proyecto_id=N
 *
 * Devuelve los campos del CRM mapeables (persona, contacto, caso) para que el
 * wrapper pueble su UI de mapeo "campo CRM → campo ViciDial". El mandante_id se
 * obtiene del request attribute inyectado por VerificarFirmaHmacMandante.
 */
final class CamposDisponiblesController
{
    public function __construct(
        private readonly ListarCamposDisponibles $listar,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $mandanteId = (int) $request->attributes->get('mandante_id');
        $proyectoId = (int) $request->query('proyecto_id', '0');

        if ($proyectoId <= 0) {
            return response()->json(['message' => 'proyecto_id requerido.'], 422);
        }

        return response()->json([
            'mandante_id' => $mandanteId,
            'proyecto_id' => $proyectoId,
            'campos' => $this->listar->execute($mandanteId, $proyectoId),
        ]);
    }
}
