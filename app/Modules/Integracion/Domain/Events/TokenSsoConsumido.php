<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Domain\Events;

final readonly class TokenSsoConsumido
{
    public function __construct(
        public string $tokenPublicId,
        public int $usuarioId,
    ) {}
}
