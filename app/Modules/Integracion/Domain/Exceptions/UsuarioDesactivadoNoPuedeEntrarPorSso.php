<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Domain\Exceptions;

use DomainException;

final class UsuarioDesactivadoNoPuedeEntrarPorSso extends DomainException
{
    public static function crear(int $usuarioId): self
    {
        return new self("El usuario {$usuarioId} está desactivado en el CRM.");
    }
}
