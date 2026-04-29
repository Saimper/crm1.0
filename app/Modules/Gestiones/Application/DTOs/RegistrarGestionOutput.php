<?php

declare(strict_types=1);

namespace App\Modules\Gestiones\Application\DTOs;

use DateTimeImmutable;

final readonly class RegistrarGestionOutput
{
    public function __construct(
        public int $id,
        public string $publicId,
        public DateTimeImmutable $creadaEn,
    ) {
    }
}
