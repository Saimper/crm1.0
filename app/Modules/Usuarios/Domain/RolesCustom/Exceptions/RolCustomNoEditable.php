<?php

declare(strict_types=1);

namespace App\Modules\Usuarios\Domain\RolesCustom\Exceptions;

use DomainException;

/**
 * Intento de editar/eliminar un rol que no es custom (rol base del sistema)
 * o que está bloqueado por estar asignado a usuarios activos.
 */
final class RolCustomNoEditable extends DomainException
{
    public static function rolBase(string $codigo): self
    {
        return new self("El rol base '{$codigo}' no puede modificarse desde la UI.");
    }

    public static function tieneAsignacionesActivas(string $codigo): self
    {
        return new self(
            "El rol custom '{$codigo}' está asignado a usuarios activos; revoca primero las asignaciones.",
        );
    }
}
