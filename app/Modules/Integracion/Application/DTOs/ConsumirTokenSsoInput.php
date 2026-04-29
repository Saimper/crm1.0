<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Application\DTOs;

final readonly class ConsumirTokenSsoInput
{
    public function __construct(
        public string $tokenClaro,
    ) {}
}
