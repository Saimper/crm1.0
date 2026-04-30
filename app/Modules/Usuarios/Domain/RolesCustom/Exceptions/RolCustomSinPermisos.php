<?php

declare(strict_types=1);

namespace App\Modules\Usuarios\Domain\RolesCustom\Exceptions;

use DomainException;

final class RolCustomSinPermisos extends DomainException
{
    public static function nuevo(): self
    {
        return new self('Un rol custom debe tener al menos un permiso asignado.');
    }
}
