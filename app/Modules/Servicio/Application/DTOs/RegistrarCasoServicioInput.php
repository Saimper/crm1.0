<?php

declare(strict_types=1);

namespace App\Modules\Servicio\Application\DTOs;

use DateTimeImmutable;

final readonly class RegistrarCasoServicioInput
{
    public function __construct(
        public int $proyectoId,
        public int $carteraId,
        public int $personaId,
        public int $estadoCasoId,
        public DateTimeImmutable $fechaIngreso,
        public int $prioridad,
        public string $codigoServicio,
        public ?int $tipoAccionServicioId = null,
        public ?int $estadoTecnicoId = null,
        public ?string $direccionServicio = null,
        public ?string $tecnicoAsignado = null,
        public ?DateTimeImmutable $fechaSolicitud = null,
        public ?DateTimeImmutable $fechaProgramada = null,
    ) {}
}
