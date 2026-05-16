<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Application\UseCases;

use App\Modules\Integracion\Application\DTOs\EmitirSanctumTokenInput;
use App\Modules\Integracion\Application\DTOs\EmitirSanctumTokenOutput;
use App\Modules\Integracion\Application\Services\AutenticadorPorJwt;

/**
 * Endpoint server-to-server: el wrapper firma un JWT y a cambio recibe un
 * Personal Access Token de Sanctum del CRM. Reusa todo el flow de validación
 * y JIT del handshake (mismo JWT, mismo anti-replay), pero en vez de iniciar
 * sesión web emite un bearer.
 */
final class EmitirSanctumTokenDesdeJwt
{
    private const NOMBRE_TOKEN_SANCTUM = 'wrapper-sso';

    public function __construct(
        private readonly AutenticadorPorJwt $autenticador,
    ) {}

    public function execute(EmitirSanctumTokenInput $input): EmitirSanctumTokenOutput
    {
        $resultado = $this->autenticador->autenticar($input->jwt);

        $token = $resultado->usuario
            ->createToken(self::NOMBRE_TOKEN_SANCTUM)
            ->plainTextToken;

        return new EmitirSanctumTokenOutput(
            accessToken: $token,
            usuarioId: (int) $resultado->usuario->id,
            mandanteId: $resultado->payload->mandanteId,
            proyectoId: $resultado->payload->proyectoId,
        );
    }
}
