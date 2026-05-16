<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Domain\Exceptions;

use DomainException;

final class SecretMandanteInvalido extends DomainException
{
    public static function crear(): self
    {
        return new self('Secret de mandante inválido (debe ser 64 hex chars).');
    }
}
