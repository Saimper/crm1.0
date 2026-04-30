<?php

declare(strict_types=1);

namespace App\Modules\Servicio\Domain\Entities;

use App\Modules\Servicio\Domain\ValueObjects\DescripcionAccion;
use App\Modules\Servicio\Domain\ValueObjects\FechaProgramada;

/**
 * Especialización CTI del Compromiso para Servicio: acción programada.
 */
final readonly class CompromisoAccionServicio
{
    private function __construct(
        public int $compromisoId,
        public int $proyectoId,
        public DescripcionAccion $descripcion,
        public FechaProgramada $fechaProgramada,
        public ?int $tipoAccionServicioId,
        public ?string $tecnicoAsignado,
    ) {}

    public static function registrar(
        int $compromisoId,
        int $proyectoId,
        DescripcionAccion $descripcion,
        FechaProgramada $fechaProgramada,
        ?int $tipoAccionServicioId,
        ?string $tecnicoAsignado,
    ): self {
        return new self(
            compromisoId: $compromisoId,
            proyectoId: $proyectoId,
            descripcion: $descripcion,
            fechaProgramada: $fechaProgramada,
            tipoAccionServicioId: $tipoAccionServicioId,
            tecnicoAsignado: $tecnicoAsignado,
        );
    }

    public static function reconstituir(
        int $compromisoId,
        int $proyectoId,
        DescripcionAccion $descripcion,
        FechaProgramada $fechaProgramada,
        ?int $tipoAccionServicioId,
        ?string $tecnicoAsignado,
    ): self {
        return new self(
            compromisoId: $compromisoId,
            proyectoId: $proyectoId,
            descripcion: $descripcion,
            fechaProgramada: $fechaProgramada,
            tipoAccionServicioId: $tipoAccionServicioId,
            tecnicoAsignado: $tecnicoAsignado,
        );
    }
}
