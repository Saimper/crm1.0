<?php

declare(strict_types=1);

namespace App\Modules\Venta\Application\DTOs;

use DateTimeImmutable;

final readonly class RegistrarCasoLeadVentaInput
{
    public function __construct(
        public int $proyectoId,
        public int $carteraId,
        public int $personaId,
        public int $estadoCasoId,
        public DateTimeImmutable $fechaIngreso,
        public int $prioridad,
        public string $codigoLead,
        public ?int $productoVentaId,
        public ?int $etapaEmbudoId,
        public string $valorEstimadoMonto,
        public string $moneda,
        public ?string $origenLead,
        public DateTimeImmutable $fechaPrimerContacto,
        public ?DateTimeImmutable $fechaEstimadaCierre,
    ) {
    }
}
