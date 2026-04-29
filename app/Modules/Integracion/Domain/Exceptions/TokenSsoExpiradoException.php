<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Domain\Exceptions;

use RuntimeException;

final class TokenSsoExpiradoException extends RuntimeException
{
    public static function crear(): self
    {
        return new self('El token SSO ha expirado.');
    }
}
