<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Application\DTOs;

final readonly class ConsumirTokenSsoOutput
{
    public function __construct(
        public int $usuarioId,
        public ?int $proyectoId,
        public ?string $redirectPath,
        public ?string $personaPublicId,
    ) {}
}
