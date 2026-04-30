<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Venta\Domain;

use App\Modules\Venta\Domain\Entities\CasoLeadVenta;
use App\Modules\Venta\Domain\Exceptions\DatosLeadInvalidos;
use App\Modules\Venta\Domain\ValueObjects\CodigoLead;
use App\Modules\Venta\Domain\ValueObjects\ValorEstimadoVenta;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class CasoLeadVentaTest extends TestCase
{
    public function test_registra_lead_valido(): void
    {
        $lead = CasoLeadVenta::registrar(
            casoId: 1,
            proyectoId: 10,
            codigoLead: new CodigoLead('LEAD-001'),
            productoVentaId: 2,
            etapaEmbudoId: 3,
            valorEstimado: new ValorEstimadoVenta('1500.00', 'USD'),
            origenLead: 'Campaña Google Ads',
            fechaPrimerContacto: new DateTimeImmutable('2026-04-10'),
            fechaEstimadaCierre: new DateTimeImmutable('2026-05-10'),
        );

        $this->assertSame('LEAD-001', $lead->codigoLead->valor);
        $this->assertSame('1500.00', $lead->valorEstimado->monto);
    }

    public function test_rechaza_cierre_anterior_a_primer_contacto(): void
    {
        $this->expectException(DatosLeadInvalidos::class);
        CasoLeadVenta::registrar(
            casoId: 1,
            proyectoId: 10,
            codigoLead: new CodigoLead('LEAD-002'),
            productoVentaId: null,
            etapaEmbudoId: null,
            valorEstimado: new ValorEstimadoVenta('500.00'),
            origenLead: null,
            fechaPrimerContacto: new DateTimeImmutable('2026-04-10'),
            fechaEstimadaCierre: new DateTimeImmutable('2026-04-01'),
        );
    }

    public function test_valor_estimado_negativo_rechazado(): void
    {
        $this->expectException(DatosLeadInvalidos::class);
        new ValorEstimadoVenta('-1.00');
    }

    public function test_valor_estimado_cero_permitido(): void
    {
        $valor = new ValorEstimadoVenta('0.00');
        $this->assertSame('0.00', $valor->monto);
    }

    public function test_codigo_lead_vacio_rechazado(): void
    {
        $this->expectException(DatosLeadInvalidos::class);
        new CodigoLead('');
    }
}
