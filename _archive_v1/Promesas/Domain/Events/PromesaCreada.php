<?php

declare(strict_types=1);

namespace App\Modules\Promesas\Domain\Events;

use App\Modules\Promesas\Domain\ValueObjects\FechaPromesa;
use App\Modules\Promesas\Domain\ValueObjects\MontoPromesa;
use DateTimeImmutable;

final readonly class PromesaCreada
{
    public function __construct(
        public int $promesaId,
        public string $publicId,
        public int $productoId,
        public int $gestionOrigenId,
        public int $usuarioId,
        public MontoPromesa $monto,
        public FechaPromesa $fecha,
        public DateTimeImmutable $creadaEn,
    ) {}
}
