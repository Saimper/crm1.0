<?php

declare(strict_types=1);

namespace App\Modules\Productos\Domain\ValueObjects;

use InvalidArgumentException;

final readonly class DiasMora
{
    public function __construct(public int $valor)
    {
        if ($valor < 0) {
            throw new InvalidArgumentException("Días de mora no puede ser negativo: {$valor}");
        }
    }
}
