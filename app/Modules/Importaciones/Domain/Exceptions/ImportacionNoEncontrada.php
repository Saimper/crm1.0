<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Domain\Exceptions;

use DomainException;

final class ImportacionNoEncontrada extends DomainException
{
    public static function conId(int $id): self
    {
        return new self("Importación {$id} no encontrada.");
    }
}
