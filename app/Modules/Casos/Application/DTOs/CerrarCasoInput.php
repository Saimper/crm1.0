<?php

declare(strict_types=1);

namespace App\Modules\Casos\Application\DTOs;

use DateTimeImmutable;

final readonly class CerrarCasoInput
{
    public function __construct(
        public int $casoId,
        public int $estadoCasoTerminalId,
        public DateTimeImmutable $cerradoEn,
    ) {
    }
}
