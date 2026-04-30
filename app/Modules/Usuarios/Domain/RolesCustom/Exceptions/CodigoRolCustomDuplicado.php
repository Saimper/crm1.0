<?php

declare(strict_types=1);

namespace App\Modules\Usuarios\Domain\RolesCustom\Exceptions;

use DomainException;

final class CodigoRolCustomDuplicado extends DomainException
{
    public static function enProyecto(string $codigo, int $proyectoId): self
    {
        return new self(
            "Ya existe un rol custom con código '{$codigo}' en el proyecto {$proyectoId}.",
        );
    }
}
