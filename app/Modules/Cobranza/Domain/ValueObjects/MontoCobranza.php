<?php

declare(strict_types=1);

namespace App\Modules\Cobranza\Domain\ValueObjects;

use App\Modules\Cobranza\Domain\Exceptions\DatosCasoCobranzaInvalidos;

final readonly class MontoCobranza
{
    public function __construct(
        public string $monto,
        public string $moneda = 'USD',
    ) {
        if (! preg_match('/^-?\d+(\.\d{1,2})?$/', $monto)) {
            throw new DatosCasoCobranzaInvalidos("Monto inválido: {$monto}. Use formato decimal con máximo 2 decimales.");
        }
        if (bccomp($monto, '0', 2) < 0) {
            throw new DatosCasoCobranzaInvalidos('El monto no puede ser negativo.');
        }
        if (! preg_match('/^[A-Z]{3}$/', $moneda)) {
            throw new DatosCasoCobranzaInvalidos("Código de moneda inválido: {$moneda}. Use ISO 4217 (3 letras mayúsculas).");
        }
    }

    public function esCero(): bool
    {
        return bccomp($this->monto, '0', 2) === 0;
    }
}
