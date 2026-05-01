<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Domain\Exceptions;

use RuntimeException;

final class JwtClaimsIncompletos extends RuntimeException
{
    public static function crear(): self
    {
        return new self('JWT con claims incompletos (jti/sub/exp/proyecto_id requeridos).');
    }
}
