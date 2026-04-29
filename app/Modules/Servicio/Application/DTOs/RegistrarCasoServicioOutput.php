<?php

declare(strict_types=1);

namespace App\Modules\Servicio\Application\DTOs;

final readonly class RegistrarCasoServicioOutput
{
    public function __construct(
        public int $casoId,
        public string $publicId,
    ) {
    }
}
