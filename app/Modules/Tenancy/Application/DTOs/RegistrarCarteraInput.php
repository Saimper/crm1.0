<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application\DTOs;

use App\Modules\Tenancy\Domain\ValueObjects\CodigoCartera;
use DateTimeImmutable;

final readonly class RegistrarCarteraInput
{
    public function __construct(
        public string $publicId,
        public int $proyectoId,
        public CodigoCartera $codigo,
        public string $nombre,
        public ?string $descripcion,
        public DateTimeImmutable $creadaEn,
    ) {}
}
