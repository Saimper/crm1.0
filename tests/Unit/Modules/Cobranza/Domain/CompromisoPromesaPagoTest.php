<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Cobranza\Domain;

use App\Modules\Cobranza\Domain\Entities\CompromisoPromesaPago;
use App\Modules\Cobranza\Domain\Exceptions\DatosPromesaInvalidos;
use App\Modules\Cobranza\Domain\ValueObjects\FechaPromesa;
use App\Modules\Cobranza\Domain\ValueObjects\MontoPromesa;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class CompromisoPromesaPagoTest extends TestCase
{
    public function test_registra_promesa_valida(): void
    {
        $promesa = CompromisoPromesaPago::registrar(
            compromisoId:     10,
            proyectoId:       5,
            monto:            new MontoPromesa('250.00', 'USD'),
            fechaVencimiento: new FechaPromesa(new DateTimeImmutable('2026-05-15')),
            tipoPagoId:       2,
        );

        $this->assertSame(10, $promesa->compromisoId);
        $this->assertSame('250.00', $promesa->monto->monto);
        $this->assertSame(2, $promesa->tipoPagoId);
    }

    public function test_monto_cero_o_negativo_rechazado(): void
    {
        $this->expectException(DatosPromesaInvalidos::class);
        new MontoPromesa('0.00');
    }

    public function test_moneda_iso_invalida_rechazada(): void
    {
        $this->expectException(DatosPromesaInvalidos::class);
        new MontoPromesa('100.00', 'USDX');
    }

    public function test_fecha_anterior_a_hoy_rechazada(): void
    {
        $this->expectException(DatosPromesaInvalidos::class);
        $fecha = new FechaPromesa(new DateTimeImmutable('2026-04-10'));
        $fecha->validarNoPasada(new DateTimeImmutable('2026-04-17'));
    }

    public function test_fecha_hoy_aceptada(): void
    {
        $fecha = new FechaPromesa(new DateTimeImmutable('2026-04-17'));
        $fecha->validarNoPasada(new DateTimeImmutable('2026-04-17 15:00:00'));
        $this->expectNotToPerformAssertions();
    }
}
