<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Gestiones;

use App\Modules\Cobranza\Domain\ValueObjects\DatosPromesaPago;
use App\Modules\Cx\Domain\ValueObjects\DatosResolucionTicket;
use App\Modules\Gestiones\Domain\ValueObjects\DatosCompromiso;
use App\Modules\Servicio\Domain\ValueObjects\DatosAccionServicio;
use App\Modules\Venta\Domain\ValueObjects\DatosCierreVenta;
use PHPUnit\Framework\TestCase;

/**
 * F34C — coverage interfaz DatosCompromiso (P2-8 ítem 3/7).
 * Las cuatro implementaciones concretas (cobranza, cx, venta, servicio)
 * implementan el contrato. Los listeners filtran via instanceof.
 */
final class DatosCompromisoTest extends TestCase
{
    public function test_promesa_pago_implementa_contrato(): void
    {
        $this->assertTrue(is_subclass_of(DatosPromesaPago::class, DatosCompromiso::class));
    }

    public function test_resolucion_ticket_implementa_contrato(): void
    {
        $this->assertTrue(is_subclass_of(DatosResolucionTicket::class, DatosCompromiso::class));
    }

    public function test_cierre_venta_implementa_contrato(): void
    {
        $this->assertTrue(is_subclass_of(DatosCierreVenta::class, DatosCompromiso::class));
    }

    public function test_accion_servicio_implementa_contrato(): void
    {
        $this->assertTrue(is_subclass_of(DatosAccionServicio::class, DatosCompromiso::class));
    }
}
