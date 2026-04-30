<?php

declare(strict_types=1);

namespace App\Modules\Cx\Domain\Entities;

use App\Modules\Cx\Domain\ValueObjects\AccionComprometida;
use App\Modules\Cx\Domain\ValueObjects\FechaLimiteSla;

/**
 * Especialización CTI del Compromiso para CX: resolución/escalamiento de un ticket.
 * El estado y transiciones viven en `Compromiso` base; aquí solo los datos específicos.
 */
final readonly class CompromisoResolucionTicket
{
    private function __construct(
        public int $compromisoId,
        public int $proyectoId,
        public AccionComprometida $accion,
        public FechaLimiteSla $fechaLimite,
        public ?int $nivelEscalamientoId,
    ) {}

    public static function registrar(
        int $compromisoId,
        int $proyectoId,
        AccionComprometida $accion,
        FechaLimiteSla $fechaLimite,
        ?int $nivelEscalamientoId,
    ): self {
        return new self(
            compromisoId: $compromisoId,
            proyectoId: $proyectoId,
            accion: $accion,
            fechaLimite: $fechaLimite,
            nivelEscalamientoId: $nivelEscalamientoId,
        );
    }

    public static function reconstituir(
        int $compromisoId,
        int $proyectoId,
        AccionComprometida $accion,
        FechaLimiteSla $fechaLimite,
        ?int $nivelEscalamientoId,
    ): self {
        return new self(
            compromisoId: $compromisoId,
            proyectoId: $proyectoId,
            accion: $accion,
            fechaLimite: $fechaLimite,
            nivelEscalamientoId: $nivelEscalamientoId,
        );
    }
}
