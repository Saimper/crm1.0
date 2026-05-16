<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Domain\ValueObjects;

/**
 * Mapeo wrapper_role (claim del JWT) → código de rol del CRM.
 *
 * Roles base del CRM: ADMIN_GLOBAL, ADMIN_MANDANTE, SUPERVISOR, GESTOR, AUDITOR.
 * super_admin del wrapper se rechaza antes (PayloadJwt) — no aterriza aquí.
 *
 * F38: admin_tenant/tenant_admin → ADMIN_MANDANTE (rol mandante-scoped via
 * tabla usuario_mandante_rol). El AutenticadorPorJwt usa esRolMandante() para
 * decidir en qué tabla pivote insertar.
 */
final readonly class MapeoRolWrapper
{
    private const POR_DEFECTO = 'GESTOR';

    /** @var array<string, string> */
    private const TABLA = [
        'admin_tenant' => 'ADMIN_MANDANTE',
        'tenant_admin' => 'ADMIN_MANDANTE',
        'admin_mandante' => 'ADMIN_MANDANTE',
        'supervisor' => 'SUPERVISOR',
        'agent' => 'GESTOR',
    ];

    /** @var list<string> Roles cuyo pivot vive en usuario_mandante_rol y no en usuario_proyecto_rol. */
    private const ROLES_MANDANTE = ['ADMIN_MANDANTE'];

    public static function aCodigoRolBase(?string $wrapperRole): string
    {
        if ($wrapperRole === null) {
            return self::POR_DEFECTO;
        }

        return self::TABLA[$wrapperRole] ?? self::POR_DEFECTO;
    }

    public static function esRolMandante(string $codigoRol): bool
    {
        return in_array($codigoRol, self::ROLES_MANDANTE, true);
    }
}
