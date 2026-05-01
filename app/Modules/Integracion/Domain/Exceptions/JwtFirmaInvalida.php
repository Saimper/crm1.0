<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Domain\Exceptions;

use RuntimeException;

final class JwtFirmaInvalida extends RuntimeException
{
    public static function crear(): self
    {
        return new self('Firma JWT inválida o expirada.');
    }
}
