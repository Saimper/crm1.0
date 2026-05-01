<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Domain\Exceptions;

use RuntimeException;

final class JwtMalFormado extends RuntimeException
{
    public static function crear(): self
    {
        return new self('JWT mal formado.');
    }
}
