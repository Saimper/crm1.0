<?php

declare(strict_types=1);

namespace App\Modules\Gestiones\Domain\ValueObjects;

use InvalidArgumentException;

final readonly class DuracionSegundos
{
    public function __construct(public int $valor)
    {
        if ($valor < 1) {
            throw new InvalidArgumentException("Duración debe ser de al menos 1 segundo: {$valor}");
        }
    }
}
