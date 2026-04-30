<?php

declare(strict_types=1);

namespace App\Modules\Casos\Domain\Events;

use App\Modules\Casos\Domain\ValueObjects\TipoCaso;
use DateTimeImmutable;

final readonly class CasoCreado
{
    public function __construct(
        public int $casoId,
        public string $publicId,
        public int $proyectoId,
        public int $carteraId,
        public int $personaId,
        public TipoCaso $tipoCaso,
        public DateTimeImmutable $creadaEn,
    ) {}
}
