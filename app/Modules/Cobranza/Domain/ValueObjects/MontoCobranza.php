<?php

declare(strict_types=1);

namespace App\Modules\Cobranza\Domain\ValueObjects;

use App\Modules\Cobranza\Domain\Exceptions\DatosCasoCobranzaInvalidos;

final readonly class MontoCobranza
{
    /** @var numeric-string */
    public string $monto;

    public function __construct(
        string $monto,
        public string $moneda = 'USD',
    ) {
        if (! is_numeric($monto) || ! preg_match('/^-?\d+(\.\d{1,3})?$/D', $monto)) {
            throw new DatosCasoCobranzaInvalidos("Monto inválido: {$monto}. Use formato decimal con máximo 3 decimales.");
        }
        if (bccomp($monto, '0', 3) < 0) {
            throw new DatosCasoCobranzaInvalidos('El monto no puede ser negativo.');
        }
        if (! preg_match('/^[A-Z]{3}$/', $moneda)) {
            throw new DatosCasoCobranzaInvalidos("Código de moneda inválido: {$moneda}. Use ISO 4217 (3 letras mayúsculas).");
        }
        $this->monto = $monto;
    }

    public function esCero(): bool
    {
        return bccomp($this->monto, '0', 3) === 0;
    }
}
