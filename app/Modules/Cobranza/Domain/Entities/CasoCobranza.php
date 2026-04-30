<?php

declare(strict_types=1);

namespace App\Modules\Cobranza\Domain\Entities;

use App\Modules\Cobranza\Domain\Exceptions\DatosCasoCobranzaInvalidos;
use App\Modules\Cobranza\Domain\ValueObjects\DiasMora;
use App\Modules\Cobranza\Domain\ValueObjects\MontoCobranza;
use App\Modules\Cobranza\Domain\ValueObjects\NumeroPrestamo;
use DateTimeImmutable;

/**
 * Especialización CTI 1:1 del núcleo Caso para operaciones de cobranza.
 * `casoId` es clave y FK al registro base en `casos`.
 */
final readonly class CasoCobranza
{
    private function __construct(
        public int $casoId,
        public int $proyectoId,
        public NumeroPrestamo $numeroPrestamo,
        public MontoCobranza $montoOriginal,
        public MontoCobranza $saldoCapital,
        public MontoCobranza $saldoInteres,
        public MontoCobranza $saldoTotal,
        public MontoCobranza $cuotaMensual,
        public int $cuotasTotales,
        public int $cuotasPagadas,
        public DiasMora $diasMora,
        public ?int $tramoMoraId,
        public DateTimeImmutable $fechaDesembolso,
        public DateTimeImmutable $fechaVencimiento,
    ) {}

    public static function registrar(
        int $casoId,
        int $proyectoId,
        NumeroPrestamo $numeroPrestamo,
        MontoCobranza $montoOriginal,
        MontoCobranza $saldoCapital,
        MontoCobranza $saldoInteres,
        MontoCobranza $saldoTotal,
        MontoCobranza $cuotaMensual,
        int $cuotasTotales,
        int $cuotasPagadas,
        DiasMora $diasMora,
        ?int $tramoMoraId,
        DateTimeImmutable $fechaDesembolso,
        DateTimeImmutable $fechaVencimiento,
    ): self {
        if ($cuotasTotales <= 0) {
            throw new DatosCasoCobranzaInvalidos("Cuotas totales debe ser > 0. Recibido: {$cuotasTotales}.");
        }
        if ($cuotasPagadas < 0 || $cuotasPagadas > $cuotasTotales) {
            throw new DatosCasoCobranzaInvalidos("Cuotas pagadas fuera de rango: {$cuotasPagadas}/{$cuotasTotales}.");
        }
        if ($montoOriginal->moneda !== $saldoCapital->moneda
            || $montoOriginal->moneda !== $saldoTotal->moneda
            || $montoOriginal->moneda !== $cuotaMensual->moneda
            || $montoOriginal->moneda !== $saldoInteres->moneda
        ) {
            throw new DatosCasoCobranzaInvalidos('Todos los montos del caso deben compartir la misma moneda.');
        }
        if ($fechaVencimiento < $fechaDesembolso) {
            throw new DatosCasoCobranzaInvalidos('La fecha de vencimiento no puede ser anterior al desembolso.');
        }

        return new self(
            casoId: $casoId,
            proyectoId: $proyectoId,
            numeroPrestamo: $numeroPrestamo,
            montoOriginal: $montoOriginal,
            saldoCapital: $saldoCapital,
            saldoInteres: $saldoInteres,
            saldoTotal: $saldoTotal,
            cuotaMensual: $cuotaMensual,
            cuotasTotales: $cuotasTotales,
            cuotasPagadas: $cuotasPagadas,
            diasMora: $diasMora,
            tramoMoraId: $tramoMoraId,
            fechaDesembolso: $fechaDesembolso,
            fechaVencimiento: $fechaVencimiento,
        );
    }

    public static function reconstituir(
        int $casoId,
        int $proyectoId,
        NumeroPrestamo $numeroPrestamo,
        MontoCobranza $montoOriginal,
        MontoCobranza $saldoCapital,
        MontoCobranza $saldoInteres,
        MontoCobranza $saldoTotal,
        MontoCobranza $cuotaMensual,
        int $cuotasTotales,
        int $cuotasPagadas,
        DiasMora $diasMora,
        ?int $tramoMoraId,
        DateTimeImmutable $fechaDesembolso,
        DateTimeImmutable $fechaVencimiento,
    ): self {
        return new self(
            casoId: $casoId,
            proyectoId: $proyectoId,
            numeroPrestamo: $numeroPrestamo,
            montoOriginal: $montoOriginal,
            saldoCapital: $saldoCapital,
            saldoInteres: $saldoInteres,
            saldoTotal: $saldoTotal,
            cuotaMensual: $cuotaMensual,
            cuotasTotales: $cuotasTotales,
            cuotasPagadas: $cuotasPagadas,
            diasMora: $diasMora,
            tramoMoraId: $tramoMoraId,
            fechaDesembolso: $fechaDesembolso,
            fechaVencimiento: $fechaVencimiento,
        );
    }
}
