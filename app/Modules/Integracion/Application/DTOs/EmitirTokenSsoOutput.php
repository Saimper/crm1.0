<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Application\DTOs;

use DateTimeImmutable;

final readonly class EmitirTokenSsoOutput
{
    public function __construct(
        public string $handshakeUrl,
        public DateTimeImmutable $expiraEn,
    ) {}
}
