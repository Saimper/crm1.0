<?php

declare(strict_types=1);

namespace App\Modules\Promesas\Application\DTOs;

use App\Modules\Promesas\Domain\ValueObjects\FechaPromesa;
use App\Modules\Promesas\Domain\ValueObjects\MontoPromesa;
use DateTimeImmutable;

final readonly class CrearPromesaDesdeGestionInput
{
    public function __construct(
        public string $publicId,
        public int $productoId,
        public int $gestionOrigenId,
        public int $usuarioId,
        public ?int $tipoPagoId,
        public MontoPromesa $monto,
        public FechaPromesa $fecha,
        public DateTimeImmutable $creadaEn,
    ) {}
}
