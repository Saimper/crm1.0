<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Domain\Exceptions;

use RuntimeException;

/**
 * El claim `exp` del JWT ya venció (con leeway). Distinto de firma inválida:
 * el token era legítimo pero llegó tarde al handshake.
 */
final class JwtExpirado extends RuntimeException
{
    public static function crear(): self
    {
        return new self('JWT expirado.');
    }
}
