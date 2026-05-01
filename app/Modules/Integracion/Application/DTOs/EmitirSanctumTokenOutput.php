<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Application\DTOs;

final readonly class EmitirSanctumTokenOutput
{
    public function __construct(
        public string $accessToken,
        public int $usuarioId,
        public int $proyectoId,
    ) {}
}
