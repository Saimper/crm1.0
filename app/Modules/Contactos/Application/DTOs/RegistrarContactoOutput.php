<?php

declare(strict_types=1);

namespace App\Modules\Contactos\Application\DTOs;

final readonly class RegistrarContactoOutput
{
    public function __construct(
        public int $id,
        public string $valor,
        public string $tipo,
    ) {}
}
