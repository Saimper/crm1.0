<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Domain\Exceptions;

use RuntimeException;

final class TokenSsoYaConsumidoException extends RuntimeException
{
    public static function crear(): self
    {
        return new self('El token SSO ya fue utilizado.');
    }
}
