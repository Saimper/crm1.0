<?php

declare(strict_types=1);

namespace App\Modules\Compromisos\Domain\Events;

use DateTimeImmutable;

abstract readonly class EventoCompromisoResuelto
{
    public function __construct(
        public int $compromisoId,
        public int $proyectoId,
        public int $casoId,
        public int $usuarioId,
        public DateTimeImmutable $fechaResolucion,
        public bool $quedanCompromisosVigentesEnCaso,
    ) {}
}
