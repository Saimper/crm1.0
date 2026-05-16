<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Domain\Exceptions;

use DomainException;

final class MandanteProyectoMismatch extends DomainException
{
    public static function crear(int $mandanteId, int $proyectoId): self
    {
        return new self("Proyecto {$proyectoId} no pertenece al mandante {$mandanteId}.");
    }
}
