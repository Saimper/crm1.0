<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Domain\Exceptions;

class ColisionCodigosCampoException extends \DomainException
{
    public function __construct(string $codigoA, string $codigoB)
    {
        parent::__construct(
            sprintf(
                'Dos columnas generan el mismo código de campo personalizado: "%s". No se puede distinguir entre ellas.',
                $codigoA === $codigoB ? $codigoA : sprintf('%s y %s', $codigoA, $codigoB),
            ),
        );
    }
}
