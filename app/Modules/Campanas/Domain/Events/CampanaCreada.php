<?php

declare(strict_types=1);

namespace App\Modules\Campanas\Domain\Events;

use DateTimeImmutable;

final readonly class CampanaCreada
{
    public function __construct(
        public int $campanaId,
        public string $publicId,
        public int $proyectoId,
        public DateTimeImmutable $creadaEn,
    ) {}
}
