<?php

declare(strict_types=1);

namespace App\Modules\Venta\Application\DTOs;

final readonly class RegistrarCasoLeadVentaOutput
{
    public function __construct(
        public int $casoId,
        public string $publicId,
    ) {
    }
}
