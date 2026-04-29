<?php

declare(strict_types=1);

namespace App\Modules\Personas\Application\DTOs;

final readonly class RegistrarPersonaOutput
{
    public function __construct(
        public int $id,
        public string $publicId,
        public string $nombreCompleto,
    ) {
    }
}
