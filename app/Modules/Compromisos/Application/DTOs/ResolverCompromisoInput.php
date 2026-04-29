<?php

declare(strict_types=1);

namespace App\Modules\Compromisos\Application\DTOs;

use DateTimeImmutable;

final readonly class ResolverCompromisoInput
{
    public function __construct(
        public int $compromisoId,
        public DateTimeImmutable $fechaResolucion,
    ) {
    }
}
