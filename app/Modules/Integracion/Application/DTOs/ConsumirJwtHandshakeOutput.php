<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Application\DTOs;

final readonly class ConsumirJwtHandshakeOutput
{
    public function __construct(
        public int $usuarioId,
        public int $mandanteId,
        public ?int $proyectoId,
        public ?string $redirectPath,
        public ?string $personaPublicId,
        public ?string $casoPublicId = null,
        public ?string $parentOrigin = null,
    ) {}
}
