<?php

declare(strict_types=1);

namespace App\Modules\Promesas\Application\DTOs;

use DateTimeImmutable;

final readonly class ResolverPromesaInput
{
    public function __construct(
        public int $promesaId,
        public DateTimeImmutable $fechaResolucion,
    ) {
    }
}
