<?php

declare(strict_types=1);

namespace App\Modules\Venta\Domain\ValueObjects;

use App\Modules\Venta\Domain\Exceptions\DatosCierreInvalidos;

final readonly class MontoCierre
{
    public function __construct(
        public string $monto,
        public string $moneda = 'USD',
    ) {
        if (! preg_match('/^\d+(\.\d{1,2})?$/', $monto)) {
            throw new DatosCierreInvalidos("Monto de cierre inválido: {$monto}.");
        }
        if (bccomp($monto, '0', 2) <= 0) {
            throw new DatosCierreInvalidos('El monto de cierre debe ser mayor que cero.');
        }
        if (! preg_match('/^[A-Z]{3}$/', $moneda)) {
            throw new DatosCierreInvalidos("Código de moneda inválido: {$moneda}.");
        }
    }
}
