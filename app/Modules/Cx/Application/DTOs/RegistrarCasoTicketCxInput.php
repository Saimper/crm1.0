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
        public ?string $asunto = null,
        public ?string $descripcion = null,
        public ?int $categoriaTicketId = null,
        public ?int $prioridadTicketId = null,
        public ?int $nivelSlaId = null,
        public ?int $nivelEscalamientoId = null,
        public ?DateTimeImmutable $fechaReporte = null,
        public ?DateTimeImmutable $fechaLimiteSla = null,
    ) {}
}
