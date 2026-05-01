<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Application\DTOs;

final readonly class EmitirSanctumTokenInput
{
    public function __construct(
        public string $jwt,
    ) {}
}
