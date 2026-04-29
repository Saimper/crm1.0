<?php

declare(strict_types=1);

namespace App\Modules\Servicio\Domain\ValueObjects;

use App\Modules\Servicio\Domain\Exceptions\DatosServicioInvalidos;

final readonly class CodigoServicio
{
    public function __construct(public string $valor)
    {
        $normalizado = trim($valor);
        if ($normalizado === '') {
            throw new DatosServicioInvalidos('El código de servicio no puede estar vacío.');
        }
        if (mb_strlen($normalizado) > 50) {
            throw new DatosServicioInvalidos('El código de servicio no puede exceder 50 caracteres.');
        }
    }

    public function __toString(): string
    {
        return $this->valor;
    }
}
