<?php

declare(strict_types=1);

namespace App\Modules\Cobranza\Domain\ValueObjects;

use App\Modules\Cobranza\Domain\Exceptions\DatosCasoCobranzaInvalidos;

final readonly class DiasMora
{
    public function __construct(public int $dias)
    {
        if ($dias < 0) {
            throw new DatosCasoCobranzaInvalidos("Los días de mora no pueden ser negativos. Recibido: {$dias}.");
        }
    }

    public function estaEnMora(): bool
    {
        return $this->dias > 0;
    }
}
