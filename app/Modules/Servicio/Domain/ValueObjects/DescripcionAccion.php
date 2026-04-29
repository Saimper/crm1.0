<?php

declare(strict_types=1);

namespace App\Modules\Servicio\Domain\ValueObjects;

use App\Modules\Servicio\Domain\Exceptions\DatosAccionInvalidos;

final readonly class DescripcionAccion
{
    public function __construct(public string $valor)
    {
        $normalizado = trim($valor);
        if ($normalizado === '') {
            throw new DatosAccionInvalidos('La descripción de la acción no puede estar vacía.');
        }
        if (mb_strlen($normalizado) > 500) {
            throw new DatosAccionInvalidos('La descripción de la acción no puede exceder 500 caracteres.');
        }
    }
}
