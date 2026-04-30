<?php

declare(strict_types=1);

namespace App\Modules\Usuarios\Domain\RolesCustom\Exceptions;

use DomainException;

/**
 * El permiso solicitado no puede asignarse a un rol custom.
 *
 * Causa típica: permisos `*.definir` (campos.definir, entidades.definir)
 * son ADMIN_GLOBAL exclusivos por §1.3 / Fase 23. Cualquier intento de
 * incluirlos en un rol custom (incluso si llega vía payload manipulado)
 * debe rechazarse.
 */
final class PermisoNoAsignableARolCustom extends DomainException
{
    public static function porCodigo(string $codigoPermiso): self
    {
        return new self(
            "El permiso '{$codigoPermiso}' no puede asignarse a un rol custom (reservado para ADMIN_GLOBAL).",
        );
    }
}
