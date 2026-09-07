<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Domain\Exceptions;

use DomainException;

final class UsuarioGlobalNoPermitidoPorSso extends DomainException
{
    public static function crear(int $usuarioId): self
    {
        return new self("El usuario {$usuarioId} tiene rol global: no puede autenticarse por SSO.");
    }
}
