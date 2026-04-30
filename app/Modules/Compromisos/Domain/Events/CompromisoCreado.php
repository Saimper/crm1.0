<?php

declare(strict_types=1);

namespace App\Modules\Compromisos\Domain\Events;

use App\Modules\Compromisos\Domain\ValueObjects\TipoCompromiso;
use DateTimeImmutable;

final readonly class CompromisoCreado
{
    public function __construct(
        public int $compromisoId,
        public string $publicId,
        public int $proyectoId,
        public int $casoId,
        public ?int $gestionOrigenId,
        public int $usuarioId,
        public TipoCompromiso $tipo,
        public DateTimeImmutable $fechaVencimiento,
        public DateTimeImmutable $creadaEn,
    ) {}
}
