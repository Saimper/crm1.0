<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Cobranza\Domain;

use App\Modules\Cobranza\Domain\Entities\CasoCobranza;
use App\Modules\Cobranza\Domain\Exceptions\DatosCasoCobranzaInvalidos;
use App\Modules\Cobranza\Domain\ValueObjects\DiasMora;
use App\Modules\Cobranza\Domain\ValueObjects\MontoCobranza;
use App\Modules\Cobranza\Domain\ValueObjects\NumeroPrestamo;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class CasoCobranzaTest extends TestCase
{
    public function test_registra_caso_cobranza_valido(): void
    {
        $caso = $this->casoBase();

        $this->assertSame(1, $caso->casoId);
        $this->assertSame('PRST-001', $caso->numeroPrestamo->valor);
        $this->assertSame(12, $caso->cuotasTotales);
        $this->assertTrue($caso->diasMora->estaEnMora());
    }

    public function test_rechaza_cuotas_totales_no_positivas(): void
    {
        $this->expectException(DatosCasoCobranzaInvalidos::class);
        CasoCobranza::registrar(
            casoId: 1,
            proyectoId: 10,
            numeroPrestamo: new NumeroPrestamo('PRST-001'),
            montoOriginal: new MontoCobranza('10000.00'),
            saldoCapital: new MontoCobranza('8000.00'),
            saldoInteres: new MontoCobranza('200.00'),
            saldoTotal: new MontoCobranza('8200.00'),
            cuotaMensual: new MontoCobranza('850.00'),
            cuotasTotales: 0,
            cuotasPagadas: 0,
            diasMora: new DiasMora(0),
            tramoMoraId: null,
            fechaDesembolso: new DateTimeImmutable('2026-01-01'),
            fechaVencimiento: new DateTimeImmutable('2027-01-01'),
        );
    }

    public function test_rechaza_cuotas_pagadas_mayores_que_totales(): void
    {
        $this->expectException(DatosCasoCobranzaInvalidos::class);
        CasoCobranza::registrar(
            casoId: 1,
            proyectoId: 10,
            numeroPrestamo: new NumeroPrestamo('PRST-001'),
            montoOriginal: new MontoCobranza('10000.00'),
            saldoCapital: new MontoCobranza('8000.00'),
            saldoInteres: new MontoCobranza('200.00'),
            saldoTotal: new MontoCobranza('8200.00'),
            cuotaMensual: new MontoCobranza('850.00'),
            cuotasTotales: 12,
            cuotasPagadas: 15,
            diasMora: new DiasMora(0),
            tramoMoraId: null,
            fechaDesembolso: new DateTimeImmutable('2026-01-01'),
            fechaVencimiento: new DateTimeImmutable('2027-01-01'),
        );
    }

    public function test_rechaza_monedas_inconsistentes(): void
    {
        $this->expectException(DatosCasoCobranzaInvalidos::class);
        CasoCobranza::registrar(
            casoId: 1,
            proyectoId: 10,
            numeroPrestamo: new NumeroPrestamo('PRST-002'),
            montoOriginal: new MontoCobranza('10000.00', 'USD'),
            saldoCapital: new MontoCobranza('8000.00', 'EUR'),
            saldoInteres: new MontoCobranza('200.00', 'USD'),
            saldoTotal: new MontoCobranza('8200.00', 'USD'),
            cuotaMensual: new MontoCobranza('850.00', 'USD'),
            cuotasTotales: 12,
            cuotasPagadas: 2,
            diasMora: new DiasMora(30),
            tramoMoraId: null,
            fechaDesembolso: new DateTimeImmutable('2026-01-01'),
            fechaVencimiento: new DateTimeImmutable('2027-01-01'),
        );
    }

    public function test_rechaza_fecha_vencimiento_anterior_a_desembolso(): void
    {
        $this->expectException(DatosCasoCobranzaInvalidos::class);
        CasoCobranza::registrar(
            casoId: 1,
            proyectoId: 10,
            numeroPrestamo: new NumeroPrestamo('PRST-003'),
            montoOriginal: new MontoCobranza('10000.00'),
            saldoCapital: new MontoCobranza('8000.00'),
            saldoInteres: new MontoCobranza('200.00'),
            saldoTotal: new MontoCobranza('8200.00'),
            cuotaMensual: new MontoCobranza('850.00'),
            cuotasTotales: 12,
            cuotasPagadas: 2,
            diasMora: new DiasMora(30),
            tramoMoraId: null,
            fechaDesembolso: new DateTimeImmutable('2026-06-01'),
            fechaVencimiento: new DateTimeImmutable('2026-01-01'),
        );
    }

    public function test_numero_prestamo_no_puede_estar_vacio(): void
    {
        $this->expectException(DatosCasoCobranzaInvalidos::class);
        new NumeroPrestamo('   ');
    }

    public function test_monto_cobranza_rechaza_negativo(): void
    {
        $this->expectException(DatosCasoCobranzaInvalidos::class);
        new MontoCobranza('-10.00');
    }

    public function test_dias_mora_rechaza_negativo(): void
    {
        $this->expectException(DatosCasoCobranzaInvalidos::class);
        new DiasMora(-1);
    }

    private function casoBase(): CasoCobranza
    {
        return CasoCobranza::registrar(
            casoId: 1,
            proyectoId: 10,
            numeroPrestamo: new NumeroPrestamo('PRST-001'),
            montoOriginal: new MontoCobranza('10000.00'),
            saldoCapital: new MontoCobranza('8000.00'),
            saldoInteres: new MontoCobranza('200.00'),
            saldoTotal: new MontoCobranza('8200.00'),
            cuotaMensual: new MontoCobranza('850.00'),
            cuotasTotales: 12,
            cuotasPagadas: 2,
            diasMora: new DiasMora(30),
            tramoMoraId: 5,
            fechaDesembolso: new DateTimeImmutable('2026-01-01'),
            fechaVencimiento: new DateTimeImmutable('2027-01-01'),
        );
    }
}
