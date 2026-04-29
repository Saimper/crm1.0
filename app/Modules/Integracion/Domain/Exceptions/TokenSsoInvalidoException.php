<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Domain\Exceptions;

use RuntimeException;

final class TokenSsoInvalidoException extends RuntimeException
{
    public static function noEncontrado(): self
    {
        return new self('Token SSO no encontrado.');
    }
}
