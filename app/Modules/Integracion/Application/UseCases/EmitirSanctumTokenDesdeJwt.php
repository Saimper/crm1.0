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

    /**
     * La habilidad que ata el token a su cliente.
     *
     * El mandante iba sólo en el NOMBRE del token, y un nombre no lo lee nadie
     * al autorizar: la ficha de persona comprobaba «¿este usuario tiene acceso
     * al proyecto?», que es una pregunta del mundo proyecto, y un usuario que
     * trabaja para dos clientes la contestaba que sí con el token del otro.
     * Como habilidad sí se comprueba, y `tokenCan()` es el mecanismo que
     * Sanctum ya trae.
     */
    public static function habilidadDeMandante(int $mandanteId): string
    {
        return 'mandante:'.$mandanteId;
    }

    /**
     * El nombre con el que se guarda el token. Lo compone una sola función
     * porque lo escribe la emisión y lo lee la baja del mandante, y el día que
     * dejen de coincidir la baja no revoca nada y no se entera nadie.
     */
    public static function nombreDeToken(int $mandanteId): string
    {
        return sprintf('%s:mandante:%d', self::NOMBRE_TOKEN_SANCTUM, $mandanteId);
    }

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
        $nombre = self::nombreDeToken($resultado->payload->mandanteId);

        $token = $resultado->usuario
            ->createToken(
                $nombre,
                [...self::HABILIDADES, self::habilidadDeMandante($resultado->payload->mandanteId)],
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
