<?php

declare(strict_types=1);

namespace App\Modules\Cobranza\Application\DTOs;

final readonly class RegistrarCasoCobranzaOutput
{
    public function __construct(
        public int $casoId,
        public string $publicId,
    ) {
    }
}
