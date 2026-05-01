<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Domain\Exceptions;

use RuntimeException;

final class WrapperRoleNoPermitido extends RuntimeException
{
    public static function crear(string $wrapperRole): self
    {
        return new self("Rol del wrapper '{$wrapperRole}' no autorizado para handshake CRM.");
    }
}
