<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Domain\Exceptions;

use DomainException;

final class UsuarioNoPerteneceAlMandante extends DomainException
{
    public static function crear(int $usuarioId, int $mandanteId): self
    {
        return new self("El usuario {$usuarioId} no está vinculado al mandante {$mandanteId}.");
    }
}
