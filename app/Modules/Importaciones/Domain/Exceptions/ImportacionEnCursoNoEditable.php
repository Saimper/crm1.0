<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Domain\Exceptions;

use App\Modules\Importaciones\Domain\Enums\EstadoImportacion;
use DomainException;

final class ImportacionEnCursoNoEditable extends DomainException
{
    public static function estado(EstadoImportacion $estado): self
    {
        return new self("Importación en estado {$estado->value} no admite la operación solicitada.");
    }
}
