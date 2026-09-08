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

    /**
     * Lo único que este token necesita poder hacer.
     *
     * `createToken()` sin segundo argumento entrega `['*']`, o sea permiso para
     * cualquier cosa que la API llegue a exponer en el futuro. El token del
     * wrapper sirve exactamente a dos endpoints, y eso es lo que se le concede.
     */
    public const HABILIDADES = ['integracion:persona', 'auth:logout'];

    public function __construct(
        private readonly AutenticadorPorJwt $autenticador,
    ) {}

    public function execute(EmitirSanctumTokenInput $input): EmitirSanctumTokenOutput
    {
        $resultado = $this->autenticador->autenticar($input->jwt);

        // El mandante va en el nombre del token porque `personal_access_tokens`
        // no tiene columna para él: sin esa marca, desactivar a un cliente no
        // permite localizar —ni revocar— los tokens que se le entregaron. Es la
        // pieza que hace posible cerrarlos, y evita una migración para lo que
        // cabe en un campo de texto que ya existe.
        $nombre = sprintf('%s:mandante:%d', self::NOMBRE_TOKEN_SANCTUM, $resultado->payload->mandanteId);

        $token = $resultado->usuario
            ->createToken(
                $nombre,
                self::HABILIDADES,
                now()->addMinutes((int) config('integracion.pat_ttl_minutos', 480)),
            )
            ->plainTextToken;

        return new EmitirSanctumTokenOutput(
            accessToken: $token,
            usuarioId: (int) $resultado->usuario->id,
            mandanteId: $resultado->payload->mandanteId,
            proyectoId: $resultado->payload->proyectoId,
        );
    }
}
