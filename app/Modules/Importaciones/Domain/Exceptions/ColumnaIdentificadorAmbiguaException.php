<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Domain\Exceptions;

class ColumnaIdentificadorAmbiguaException extends \DomainException
{
    public function __construct()
    {
        parent::__construct(
            'Se ha seleccionado más de una columna como identificador de persona. Solo se permite una columna identificadora.'
        );
    }
}
