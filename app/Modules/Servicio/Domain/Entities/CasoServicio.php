<?php

declare(strict_types=1);

namespace App\Modules\Servicio\Domain\Entities;

use App\Modules\Servicio\Domain\Exceptions\DatosServicioInvalidos;
use App\Modules\Servicio\Domain\ValueObjects\CodigoServicio;
use DateTimeImmutable;

/**
 * Especialización CTI 1:1 del núcleo Caso para servicio técnico.
 */
final readonly class CasoServicio
{
    private function __construct(
        public int $casoId,
        public int $proyectoId,
        public CodigoServicio $codigoServicio,
        public ?int $tipoAccionServicioId,
        public ?int $estadoTecnicoId,
        public ?string $direccionServicio,
        public ?string $tecnicoAsignado,
        public DateTimeImmutable $fechaSolicitud,
        public ?DateTimeImmutable $fechaProgramada,
    ) {}

    public static function registrar(
        int $casoId,
        int $proyectoId,
        CodigoServicio $codigoServicio,
        ?int $tipoAccionServicioId,
        ?int $estadoTecnicoId,
        ?string $direccionServicio,
        ?string $tecnicoAsignado,
        DateTimeImmutable $fechaSolicitud,
        ?DateTimeImmutable $fechaProgramada,
    ): self {
        if ($fechaProgramada !== null && $fechaProgramada < $fechaSolicitud) {
            throw new DatosServicioInvalidos('La fecha programada no puede ser anterior a la fecha de solicitud.');
        }
        if ($direccionServicio !== null && mb_strlen($direccionServicio) > 500) {
            throw new DatosServicioInvalidos('La dirección de servicio no puede exceder 500 caracteres.');
        }

        return new self(
            casoId: $casoId,
            proyectoId: $proyectoId,
            codigoServicio: $codigoServicio,
            tipoAccionServicioId: $tipoAccionServicioId,
            estadoTecnicoId: $estadoTecnicoId,
            direccionServicio: $direccionServicio,
            tecnicoAsignado: $tecnicoAsignado,
            fechaSolicitud: $fechaSolicitud,
            fechaProgramada: $fechaProgramada,
        );
    }

    public static function reconstituir(
        int $casoId,
        int $proyectoId,
        CodigoServicio $codigoServicio,
        ?int $tipoAccionServicioId,
        ?int $estadoTecnicoId,
        ?string $direccionServicio,
        ?string $tecnicoAsignado,
        DateTimeImmutable $fechaSolicitud,
        ?DateTimeImmutable $fechaProgramada,
    ): self {
        return new self(
            casoId: $casoId,
            proyectoId: $proyectoId,
            codigoServicio: $codigoServicio,
            tipoAccionServicioId: $tipoAccionServicioId,
            estadoTecnicoId: $estadoTecnicoId,
            direccionServicio: $direccionServicio,
            tecnicoAsignado: $tecnicoAsignado,
            fechaSolicitud: $fechaSolicitud,
            fechaProgramada: $fechaProgramada,
        );
    }
}
