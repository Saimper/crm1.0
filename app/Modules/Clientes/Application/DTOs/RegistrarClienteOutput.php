<?php

declare(strict_types=1);

namespace App\Modules\Clientes\Application\DTOs;

final readonly class RegistrarClienteOutput
{
    public function __construct(
        public int $id,
        public string $publicId,
        public string $nombreCompleto,
    ) {}
}
