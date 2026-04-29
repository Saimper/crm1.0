<?php

declare(strict_types=1);

namespace App\Modules\Cobranza\Domain\ValueObjects;

use App\Modules\Cobranza\Domain\Exceptions\DatosPromesaInvalidos;

final readonly class MontoPromesa
{
    public function __construct(
        public string $monto,
        public string $moneda = 'USD',
    ) {
        if (! preg_match('/^\d+(\.\d{1,2})?$/', $monto)) {
            throw new DatosPromesaInvalidos("Monto de promesa inválido: {$monto}.");
        }
        if (bccomp($monto, '0', 2) <= 0) {
            throw new DatosPromesaInvalidos('El monto de una promesa debe ser mayor que cero.');
        }
        if (! preg_match('/^[A-Z]{3}$/', $moneda)) {
            throw new DatosPromesaInvalidos("Código de moneda inválido: {$moneda}.");
        }
    }
}
