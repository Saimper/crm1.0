<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Domain\Exceptions;

use RuntimeException;

final class JwtTtlExcedido extends RuntimeException
{
    public static function crear(int $maxSegundos): self
    {
        return new self("JWT con TTL excedido (máx {$maxSegundos}s).");
    }
}
