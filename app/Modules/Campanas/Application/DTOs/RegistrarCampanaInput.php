<?php

declare(strict_types=1);

namespace App\Modules\Campanas\Application\DTOs;

use App\Modules\Campanas\Domain\ValueObjects\CodigoCampana;
use DateTimeImmutable;

final readonly class RegistrarCampanaInput
{
    public function __construct(
        public string $publicId,
        public int $proyectoId,
        public CodigoCampana $codigo,
        public string $nombre,
        public ?string $descripcion,
        public DateTimeImmutable $fechaInicio,
        public ?DateTimeImmutable $fechaFin,
        public ?int $creadaPorId,
        public DateTimeImmutable $creadaEn,
    ) {
    }
}
