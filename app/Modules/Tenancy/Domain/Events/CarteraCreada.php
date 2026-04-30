<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Domain\Events;

use DateTimeImmutable;

final readonly class CarteraCreada
{
    public function __construct(
        public int $carteraId,
        public string $publicId,
        public int $proyectoId,
        public DateTimeImmutable $creadaEn,
    ) {}
}
