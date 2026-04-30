<?php

declare(strict_types=1);

namespace App\Modules\Venta\Domain\Entities;

use App\Modules\Venta\Domain\ValueObjects\FechaCierreEstimada;
use App\Modules\Venta\Domain\ValueObjects\MontoCierre;

/**
 * Especialización CTI del Compromiso para Venta: cierre estimado.
 */
final readonly class CompromisoCierreVenta
{
    private function __construct(
        public int $compromisoId,
        public int $proyectoId,
        public MontoCierre $monto,
        public FechaCierreEstimada $fechaEstimada,
        public ?int $etapaEmbudoId,
    ) {}

    public static function registrar(
        int $compromisoId,
        int $proyectoId,
        MontoCierre $monto,
        FechaCierreEstimada $fechaEstimada,
        ?int $etapaEmbudoId,
    ): self {
        return new self(
            compromisoId: $compromisoId,
            proyectoId: $proyectoId,
            monto: $monto,
            fechaEstimada: $fechaEstimada,
            etapaEmbudoId: $etapaEmbudoId,
        );
    }

    public static function reconstituir(
        int $compromisoId,
        int $proyectoId,
        MontoCierre $monto,
        FechaCierreEstimada $fechaEstimada,
        ?int $etapaEmbudoId,
    ): self {
        return new self(
            compromisoId: $compromisoId,
            proyectoId: $proyectoId,
            monto: $monto,
            fechaEstimada: $fechaEstimada,
            etapaEmbudoId: $etapaEmbudoId,
        );
    }
}
