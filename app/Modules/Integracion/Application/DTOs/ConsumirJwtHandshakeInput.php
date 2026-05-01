<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Application\DTOs;

final readonly class ConsumirJwtHandshakeInput
{
    public function __construct(
        public string $jwt,
    ) {}
}
