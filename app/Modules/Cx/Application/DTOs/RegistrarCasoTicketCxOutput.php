<?php

declare(strict_types=1);

namespace App\Modules\Cx\Application\DTOs;

final readonly class RegistrarCasoTicketCxOutput
{
    public function __construct(
        public int $casoId,
        public string $publicId,
    ) {
    }
}
