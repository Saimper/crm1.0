<?php

declare(strict_types=1);

namespace App\Modules\Asignaciones\Domain\Events;

use DateTimeImmutable;

final readonly class AsignacionCerrada
{
    public function __construct(
        public int $asignacionId,
        public int $proyectoId,
        public int $casoId,
        public int $usuarioId,
        public DateTimeImmutable $cerradaEn,
    ) {}
}
