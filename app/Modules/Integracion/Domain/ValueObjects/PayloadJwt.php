<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Domain\ValueObjects;

use App\Modules\Integracion\Domain\Exceptions\JwtClaimsIncompletos;
use App\Modules\Integracion\Domain\Exceptions\JwtTtlExcedido;
use App\Modules\Integracion\Domain\Exceptions\WrapperRoleNoPermitido;
use DateTimeImmutable;

final readonly class PayloadJwt
{
    private const TTL_MAX_SEGUNDOS = 60;

    private const WRAPPER_ROLE_BLOQUEADO = 'super_admin';

    private const AUD_ESPERADO = 'crm';

    private function __construct(
        public string $jti,
        public string $email,
        public string $name,
        public int $mandanteId,
        public ?int $proyectoId,
        public DateTimeImmutable $expiraEn,
        public ?string $wrapperRole,
        public ?string $redirectPath,
        public ?string $identificacion,
        public ?string $tipoIdentificacionCodigo,
        public ?string $numeroPrestamo,
        public ?string $iss,
        public ?string $aud,
        public ?string $syncRef,
    ) {}

    public static function desdeClaims(object $claims, DateTimeImmutable $ahora): self
    {
        $jti = isset($claims->jti) ? (string) $claims->jti : '';
        $email = isset($claims->sub) ? (string) $claims->sub : '';
        $exp = isset($claims->exp) ? (int) $claims->exp : 0;
        $mandanteId = isset($claims->mandante_id) ? (int) $claims->mandante_id : 0;
        $proyectoId = isset($claims->proyecto_id) ? (int) $claims->proyecto_id : 0;

        if ($jti === '' || $email === '' || $exp === 0 || $mandanteId === 0) {
            throw JwtClaimsIncompletos::crear();
        }

        if ($exp - $ahora->getTimestamp() > self::TTL_MAX_SEGUNDOS) {
            throw JwtTtlExcedido::crear(self::TTL_MAX_SEGUNDOS);
        }

        $iss = isset($claims->iss) ? (string) $claims->iss : null;
        $aud = isset($claims->aud) ? (string) $claims->aud : null;

        if ($iss !== null && $iss !== "wrapper:{$mandanteId}") {
            throw JwtClaimsIncompletos::crear();
        }

        if ($aud !== null && $aud !== self::AUD_ESPERADO) {
            throw JwtClaimsIncompletos::crear();
        }

        $wrapperRole = isset($claims->wrapper_role) ? (string) $claims->wrapper_role : null;
        if ($wrapperRole === self::WRAPPER_ROLE_BLOQUEADO) {
            throw WrapperRoleNoPermitido::crear($wrapperRole);
        }

        $name = isset($claims->name) ? (string) $claims->name : $email;
        $redirectPath = isset($claims->redirect_path) ? (string) $claims->redirect_path : null;
        $syncRef = isset($claims->sync_ref) ? (string) $claims->sync_ref : null;

        return new self(
            jti: $jti,
            email: strtolower(trim($email)),
            name: $name,
            mandanteId: $mandanteId,
            proyectoId: $proyectoId > 0 ? $proyectoId : null,
            expiraEn: (new DateTimeImmutable)->setTimestamp($exp),
            wrapperRole: $wrapperRole,
            redirectPath: $redirectPath,
            identificacion: self::claveDeFicha($claims, 'identificacion'),
            tipoIdentificacionCodigo: self::claveDeFicha($claims, 'tipo_identificacion_codigo'),
            numeroPrestamo: self::claveDeFicha($claims, 'numero_prestamo'),
            iss: $iss,
            aud: $aud,
            syncRef: $syncRef,
        );
    }

    /**
     * Las claves con las que se localiza la ficha llegan tal cual las tiene el
     * lead en ViciDial (vendor_lead_code cargado a mano, con espacios de más).
     * Se comparan contra columnas que el CRM guarda recortadas, así que aquí se
     * recortan también; una clave vacía no es una clave, es ausencia de clave.
     */
    private static function claveDeFicha(object $claims, string $claim): ?string
    {
        if (! isset($claims->{$claim})) {
            return null;
        }

        $valor = trim((string) $claims->{$claim});

        return $valor === '' ? null : $valor;
    }
}
