<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Domain\Exceptions;

use RuntimeException;

final class ProyectoSsoNoConfigurado extends RuntimeException
{
    public static function crear(int $proyectoId): self
    {
        return new self("Proyecto {$proyectoId} sin sso_secret configurado.");
    }
}
