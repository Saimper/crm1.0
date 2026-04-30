<?php

declare(strict_types=1);

namespace App\Modules\Cobranza\Application\DTOs;

use DateTimeImmutable;

final readonly class RegistrarCasoCobranzaInput
{
    public function __construct(
        public int $proyectoId,
        public int $carteraId,
        public int $personaId,
        public int $estadoCasoId,
        public DateTimeImmutable $fechaIngreso,
        public int $prioridad,
        public string $numeroPrestamo,
        public string $moneda,
        public string $montoOriginal,
        public string $saldoCapital,
        public string $saldoInteres,
        public string $saldoTotal,
        public string $cuotaMensual,
        public int $cuotasTotales,
        public int $cuotasPagadas,
        public int $diasMora,
        public DateTimeImmutable $fechaDesembolso,
        public DateTimeImmutable $fechaVencimiento,
    ) {}
}
