<?php

declare(strict_types=1);

namespace App\Modules\Asignaciones\Application\DTOs;

use DateTimeImmutable;

final readonly class RegistrarAsignacionInput
{
    public function __construct(
        public string $publicId,
        public int $proyectoId,
        public int $campanaId,
        public int $casoId,
        public int $usuarioId,
        public DateTimeImmutable $fechaAsignacion,
        public int $prioridad,
        public DateTimeImmutable $creadaEn,
    ) {}
}
