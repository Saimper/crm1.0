<?php

declare(strict_types=1);

namespace App\Modules\Venta\Domain\ValueObjects;

use App\Modules\Venta\Domain\Exceptions\DatosLeadInvalidos;

final readonly class CodigoLead
{
    public function __construct(public string $valor)
    {
        $normalizado = trim($valor);
        if ($normalizado === '') {
            throw new DatosLeadInvalidos('El código de lead no puede estar vacío.');
        }
        if (mb_strlen($normalizado) > 50) {
            throw new DatosLeadInvalidos('El código de lead no puede exceder 50 caracteres.');
        }
    }

    public function __toString(): string
    {
        return $this->valor;
    }
}
