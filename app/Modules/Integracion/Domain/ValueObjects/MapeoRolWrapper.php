<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Domain\ValueObjects;

/**
 * Mapeo wrapper_role (claim del JWT) → código de rol base F22 del CRM.
 *
 * Roles base del CRM: ADMIN_GLOBAL, SUPERVISOR, GESTOR, AUDITOR.
 * super_admin del wrapper se rechaza antes (PayloadJwt) — no aterriza aquí.
 */
final readonly class MapeoRolWrapper
{
    private const POR_DEFECTO = 'GESTOR';

    /** @var array<string, string> */
    private const TABLA = [
        'tenant_admin' => 'SUPERVISOR',
        'agent' => 'GESTOR',
    ];

    public static function aCodigoRolBase(?string $wrapperRole): string
    {
        if ($wrapperRole === null) {
            return self::POR_DEFECTO;
        }

        return self::TABLA[$wrapperRole] ?? self::POR_DEFECTO;
    }
}
