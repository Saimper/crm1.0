<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Application\DTOs;

final readonly class EmitirTokenSsoInput
{
    public function __construct(
        public int $usuarioId,
        public ?int $proyectoId,
        public ?string $identificacion,
        public ?string $tipoIdentificacionCodigo,
        public ?string $redirectPath,
        public ?string $ipOrigen,
        public ?string $userAgent,
    ) {}
}
