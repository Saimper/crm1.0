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
        $identificacion = isset($claims->identificacion) ? (string) $claims->identificacion : null;
        $tipoIdentificacionCodigo = isset($claims->tipo_identificacion_codigo) ? (string) $claims->tipo_identificacion_codigo : null;
        $numeroPrestamo = isset($claims->numero_prestamo) ? (string) $claims->numero_prestamo : null;

        return new self(
            jti: $jti,
            email: strtolower(trim($email)),
            name: $name,
            mandanteId: $mandanteId,
            proyectoId: $proyectoId > 0 ? $proyectoId : null,
            expiraEn: (new DateTimeImmutable)->setTimestamp($exp),
            wrapperRole: $wrapperRole,
            redirectPath: $redirectPath,
            identificacion: $identificacion,
            tipoIdentificacionCodigo: $tipoIdentificacionCodigo,
            numeroPrestamo: $numeroPrestamo,
            iss: $iss,
            aud: $aud,
        );
    }
}
