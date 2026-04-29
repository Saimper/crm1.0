<?php

declare(strict_types=1);

namespace App\Modules\Casos\Domain\Events;

use DateTimeImmutable;

final readonly class CasoCerrado
{
    public function __construct(
        public int $casoId,
        public int $proyectoId,
        public int $estadoCasoId,
        public DateTimeImmutable $cerradoEn,
    ) {
    }
}
