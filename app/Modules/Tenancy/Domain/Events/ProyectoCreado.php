<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Domain\Events;

use App\Modules\Tenancy\Domain\ValueObjects\TipoOperacion;
use DateTimeImmutable;

final readonly class ProyectoCreado
{
    public function __construct(
        public int $proyectoId,
        public string $publicId,
        public int $mandanteId,
        public TipoOperacion $tipoOperacion,
        public DateTimeImmutable $creadaEn,
    ) {
    }
}
