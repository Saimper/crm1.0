<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application\DTOs;

final readonly class RegistrarProyectoOutput
{
    public function __construct(
        public int $id,
        public string $publicId,
        public string $codigo,
        public string $nombre,
        public string $tipoOperacion,
    ) {
    }
}
