<?php

declare(strict_types=1);

namespace App\Modules\Cobranza\Domain\ValueObjects;

use App\Modules\Cobranza\Domain\Exceptions\DatosCasoCobranzaInvalidos;

final readonly class NumeroPrestamo
{
    public function __construct(public string $valor)
    {
        $normalizado = trim($valor);
        if ($normalizado === '') {
            throw new DatosCasoCobranzaInvalidos('El número de préstamo no puede estar vacío.');
        }
        if (mb_strlen($normalizado) > 100) {
            throw new DatosCasoCobranzaInvalidos('El número de préstamo no puede exceder 100 caracteres.');
        }
    }

    public function __toString(): string
    {
        return $this->valor;
    }
}
