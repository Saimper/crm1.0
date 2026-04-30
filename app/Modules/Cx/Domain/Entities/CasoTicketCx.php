<?php

declare(strict_types=1);

namespace App\Modules\Cx\Domain\Entities;

use App\Modules\Cx\Domain\Exceptions\DatosTicketInvalidos;
use App\Modules\Cx\Domain\ValueObjects\AsuntoTicket;
use App\Modules\Cx\Domain\ValueObjects\CodigoTicket;
use DateTimeImmutable;

/**
 * Especialización CTI 1:1 del núcleo Caso para operaciones de CX (ticket de soporte).
 */
final readonly class CasoTicketCx
{
    private function __construct(
        public int $casoId,
        public int $proyectoId,
        public CodigoTicket $codigoTicket,
        public AsuntoTicket $asunto,
        public ?string $descripcion,
        public ?int $categoriaTicketId,
        public ?int $prioridadTicketId,
        public ?int $nivelSlaId,
        public ?int $nivelEscalamientoId,
        public DateTimeImmutable $fechaReporte,
        public ?DateTimeImmutable $fechaLimiteSla,
    ) {}

    public static function registrar(
        int $casoId,
        int $proyectoId,
        CodigoTicket $codigoTicket,
        AsuntoTicket $asunto,
        ?string $descripcion,
        ?int $categoriaTicketId,
        ?int $prioridadTicketId,
        ?int $nivelSlaId,
        ?int $nivelEscalamientoId,
        DateTimeImmutable $fechaReporte,
        ?DateTimeImmutable $fechaLimiteSla,
    ): self {
        if ($fechaLimiteSla !== null && $fechaLimiteSla < $fechaReporte) {
            throw new DatosTicketInvalidos('La fecha límite de SLA no puede ser anterior a la fecha de reporte.');
        }

        return new self(
            casoId: $casoId,
            proyectoId: $proyectoId,
            codigoTicket: $codigoTicket,
            asunto: $asunto,
            descripcion: $descripcion,
            categoriaTicketId: $categoriaTicketId,
            prioridadTicketId: $prioridadTicketId,
            nivelSlaId: $nivelSlaId,
            nivelEscalamientoId: $nivelEscalamientoId,
            fechaReporte: $fechaReporte,
            fechaLimiteSla: $fechaLimiteSla,
        );
    }

    public static function reconstituir(
        int $casoId,
        int $proyectoId,
        CodigoTicket $codigoTicket,
        AsuntoTicket $asunto,
        ?string $descripcion,
        ?int $categoriaTicketId,
        ?int $prioridadTicketId,
        ?int $nivelSlaId,
        ?int $nivelEscalamientoId,
        DateTimeImmutable $fechaReporte,
        ?DateTimeImmutable $fechaLimiteSla,
    ): self {
        return new self(
            casoId: $casoId,
            proyectoId: $proyectoId,
            codigoTicket: $codigoTicket,
            asunto: $asunto,
            descripcion: $descripcion,
            categoriaTicketId: $categoriaTicketId,
            prioridadTicketId: $prioridadTicketId,
            nivelSlaId: $nivelSlaId,
            nivelEscalamientoId: $nivelEscalamientoId,
            fechaReporte: $fechaReporte,
            fechaLimiteSla: $fechaLimiteSla,
        );
    }
}
