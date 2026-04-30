<?php

declare(strict_types=1);

namespace App\Modules\Campanas\Application\DTOs;

final readonly class RegistrarCampanaOutput
{
    public function __construct(
        public int $id,
        public string $publicId,
        public string $codigo,
    ) {}
}
