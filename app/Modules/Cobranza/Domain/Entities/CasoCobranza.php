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
 *
 * F35-D: solo `numeroPrestamo` (clave única) y `proyectoId` son obligatorios.
 * El resto puede ser null cuando el admin del proyecto no configura el campo
 * en su plantilla — el dato se guardará vía Campos Personalizados §7.
 * Las validaciones cruzadas (moneda consistente, fecha venc >= des, cuotas pagadas <= totales)
 * solo aplican cuando ambos lados están presentes.
 */
final readonly class CasoCobranza
{
    private function __construct(
        public int $casoId,
        public int $proyectoId,
        public NumeroPrestamo $numeroPrestamo,
        public ?MontoCobranza $montoOriginal,
        public ?MontoCobranza $saldoCapital,
        public ?MontoCobranza $saldoInteres,
        public ?MontoCobranza $saldoTotal,
        public ?MontoCobranza $cuotaMensual,
        public ?int $cuotasTotales,
        public ?int $cuotasPagadas,
        public ?DiasMora $diasMora,
        public ?int $tramoMoraId,
        public ?DateTimeImmutable $fechaDesembolso,
        public ?DateTimeImmutable $fechaVencimiento,
    ) {}

    public static function registrar(
        int $casoId,
        int $proyectoId,
        NumeroPrestamo $numeroPrestamo,
        ?MontoCobranza $montoOriginal,
        ?MontoCobranza $saldoCapital,
        ?MontoCobranza $saldoInteres,
        ?MontoCobranza $saldoTotal,
        ?MontoCobranza $cuotaMensual,
        ?int $cuotasTotales,
        ?int $cuotasPagadas,
        ?DiasMora $diasMora,
        ?int $tramoMoraId,
        ?DateTimeImmutable $fechaDesembolso,
        ?DateTimeImmutable $fechaVencimiento,
    ): self {
        if ($cuotasTotales !== null && $cuotasTotales <= 0) {
            throw new DatosCasoCobranzaInvalidos("Cuotas totales debe ser > 0. Recibido: {$cuotasTotales}.");
        }
        if ($cuotasTotales !== null && $cuotasPagadas !== null && ($cuotasPagadas < 0 || $cuotasPagadas > $cuotasTotales)) {
            throw new DatosCasoCobranzaInvalidos("Cuotas pagadas fuera de rango: {$cuotasPagadas}/{$cuotasTotales}.");
        }
        $monedas = array_filter([
            $montoOriginal?->moneda,
            $saldoCapital?->moneda,
            $saldoInteres?->moneda,
            $saldoTotal?->moneda,
            $cuotaMensual?->moneda,
        ]);
        if (count(array_unique($monedas)) > 1) {
            throw new DatosCasoCobranzaInvalidos('Todos los montos del caso deben compartir la misma moneda.');
        }
        if ($fechaDesembolso !== null && $fechaVencimiento !== null && $fechaVencimiento < $fechaDesembolso) {
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
        ?MontoCobranza $montoOriginal,
        ?MontoCobranza $saldoCapital,
        ?MontoCobranza $saldoInteres,
        ?MontoCobranza $saldoTotal,
        ?MontoCobranza $cuotaMensual,
        ?int $cuotasTotales,
        ?int $cuotasPagadas,
        ?DiasMora $diasMora,
        ?int $tramoMoraId,
        ?DateTimeImmutable $fechaDesembolso,
        ?DateTimeImmutable $fechaVencimiento,
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
