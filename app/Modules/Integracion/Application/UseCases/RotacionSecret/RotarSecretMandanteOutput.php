<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Application\UseCases\RotacionSecret;

use DateTimeImmutable;

final readonly class RotarSecretMandanteOutput
{
    public function __construct(
        public int $mandanteId,
        public string $secretNuevo,
        public ?DateTimeImmutable $secretAnteriorExpiraEn,
    ) {}
}
