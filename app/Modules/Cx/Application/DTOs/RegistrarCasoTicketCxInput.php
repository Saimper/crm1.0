<?php

declare(strict_types=1);

namespace App\Modules\Cx\Application\DTOs;

use DateTimeImmutable;

final readonly class RegistrarCasoTicketCxInput
{
    public function __construct(
        public int $proyectoId,
        public int $carteraId,
        public int $personaId,
        public int $estadoCasoId,
        public DateTimeImmutable $fechaIngreso,
        public int $prioridad,
        public string $codigoTicket,
        public string $asunto,
        public ?string $descripcion,
        public ?int $categoriaTicketId,
        public ?int $prioridadTicketId,
        public ?int $nivelSlaId,
        public ?int $nivelEscalamientoId,
        public DateTimeImmutable $fechaReporte,
        public ?DateTimeImmutable $fechaLimiteSla,
    ) {}
}
