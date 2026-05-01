<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Venta;

use App\Modules\Venta\Domain\Entities\CompromisoCierreVenta;
use App\Modules\Venta\Domain\ValueObjects\FechaCierreEstimada;
use App\Modules\Venta\Domain\ValueObjects\MontoCierre;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * F34C — coverage entidad CompromisoCierreVenta (P2-8 ítem 6/7).
 */
final class CompromisoCierreVentaTest extends TestCase
{
    public function test_registrar_persiste_atributos(): void
    {
        $monto = new MontoCierre('1500.00', 'USD');
        $fecha = new FechaCierreEstimada(new DateTimeImmutable('+10 days'));

        $c = CompromisoCierreVenta::registrar(
            compromisoId: 22,
            proyectoId: 9,
            monto: $monto,
            fechaEstimada: $fecha,
            etapaEmbudoId: 5,
        );

        $this->assertSame(22, $c->compromisoId);
        $this->assertSame(9, $c->proyectoId);
        $this->assertSame($monto, $c->monto);
        $this->assertSame($fecha, $c->fechaEstimada);
        $this->assertSame(5, $c->etapaEmbudoId);
    }

    public function test_reconstituir_admite_etapa_null(): void
    {
        $c = CompromisoCierreVenta::reconstituir(
            compromisoId: 1,
            proyectoId: 1,
            monto: new MontoCierre('100.00'),
            fechaEstimada: new FechaCierreEstimada(new DateTimeImmutable('+1 day')),
            etapaEmbudoId: null,
        );

        $this->assertNull($c->etapaEmbudoId);
    }
}
