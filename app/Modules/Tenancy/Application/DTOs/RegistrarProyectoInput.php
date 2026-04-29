<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application\DTOs;

use App\Modules\Tenancy\Domain\ValueObjects\CodigoProyecto;
use App\Modules\Tenancy\Domain\ValueObjects\TipoOperacion;
use DateTimeImmutable;

final readonly class RegistrarProyectoInput
{
    public function __construct(
        public string $publicId,
        public int $mandanteId,
        public CodigoProyecto $codigo,
        public string $nombre,
        public ?string $descripcion,
        public TipoOperacion $tipoOperacion,
        public ?DateTimeImmutable $fechaInicio,
        public ?DateTimeImmutable $fechaFin,
        public DateTimeImmutable $creadaEn,
    ) {
    }
}
