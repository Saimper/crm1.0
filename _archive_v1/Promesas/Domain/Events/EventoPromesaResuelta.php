<?php

declare(strict_types=1);

namespace App\Modules\Promesas\Domain\Events;

use DateTimeImmutable;

abstract readonly class EventoPromesaResuelta
{
    public function __construct(
        public int $promesaId,
        public int $productoId,
        public int $usuarioId,
        public DateTimeImmutable $fechaResolucion,
        public bool $quedanPromesasVigentes,
    ) {}
}
