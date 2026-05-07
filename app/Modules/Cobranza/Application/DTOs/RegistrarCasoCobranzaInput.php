<?php

declare(strict_types=1);

namespace App\Modules\Cobranza\Application\DTOs;

use DateTimeImmutable;

/**
 * F35-D: campos del CTI son nullable salvo `numeroPrestamo` (clave única del caso en proyecto).
 * El admin del proyecto decide qué pedir vía Campos Personalizados §7. Lo no llenado queda null.
 */
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
        public string $moneda = 'USD',
        public ?string $montoOriginal = null,
        public ?string $saldoCapital = null,
        public ?string $saldoInteres = null,
        public ?string $saldoTotal = null,
        public ?string $cuotaMensual = null,
        public ?int $cuotasTotales = null,
        public ?int $cuotasPagadas = null,
        public ?int $diasMora = null,
        public ?DateTimeImmutable $fechaDesembolso = null,
        public ?DateTimeImmutable $fechaVencimiento = null,
    ) {}
}
