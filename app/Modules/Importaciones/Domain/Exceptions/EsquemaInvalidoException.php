<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Domain\Exceptions;

class EsquemaInvalidoException extends \DomainException implements MensajeAptoParaPantalla
{
    public function __construct(string $mensaje)
    {
        parent::__construct($mensaje);
    }
}
