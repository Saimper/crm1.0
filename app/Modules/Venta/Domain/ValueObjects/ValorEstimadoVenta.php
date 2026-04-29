<?php

declare(strict_types=1);

namespace App\Modules\Venta\Domain\ValueObjects;

use App\Modules\Venta\Domain\Exceptions\DatosLeadInvalidos;

final readonly class ValorEstimadoVenta
{
    public function __construct(
        public string $monto,
        public string $moneda = 'USD',
    ) {
        if (! preg_match('/^\d+(\.\d{1,2})?$/', $monto)) {
            throw new DatosLeadInvalidos("Valor estimado inválido: {$monto}.");
        }
        if (bccomp($monto, '0', 2) < 0) {
            throw new DatosLeadInvalidos('El valor estimado no puede ser negativo.');
        }
        if (! preg_match('/^[A-Z]{3}$/', $moneda)) {
            throw new DatosLeadInvalidos("Código de moneda inválido: {$moneda}.");
        }
    }
}
