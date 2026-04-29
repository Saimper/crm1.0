<?php

declare(strict_types=1);

namespace App\Modules\Promesas\Domain\ValueObjects;

use InvalidArgumentException;

final readonly class MontoPromesa
{
    public function __construct(public string $valor)
    {
        if (preg_match('/^\d{1,13}(\.\d{1,2})?$/', $valor) !== 1) {
            throw new InvalidArgumentException("Monto con formato inválido: {$valor}");
        }
        if ((float) $valor <= 0.0) {
            throw new InvalidArgumentException("Monto debe ser mayor a cero: {$valor}");
        }
    }

    public function asDecimal(): string
    {
        return $this->valor;
    }
}
