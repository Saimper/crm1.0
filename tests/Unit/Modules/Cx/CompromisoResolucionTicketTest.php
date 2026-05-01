<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Cx;

use App\Modules\Cx\Domain\Entities\CompromisoResolucionTicket;
use App\Modules\Cx\Domain\ValueObjects\AccionComprometida;
use App\Modules\Cx\Domain\ValueObjects\FechaLimiteSla;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * F34C — coverage entidad CompromisoResolucionTicket (P2-8 ítem 4/7).
 */
final class CompromisoResolucionTicketTest extends TestCase
{
    public function test_registrar_persiste_atributos(): void
    {
        $accion = new AccionComprometida('Llamar al cliente');
        $sla = new FechaLimiteSla(new DateTimeImmutable('+1 day'));

        $c = CompromisoResolucionTicket::registrar(
            compromisoId: 99,
            proyectoId: 7,
            accion: $accion,
            fechaLimite: $sla,
            nivelEscalamientoId: null,
        );

        $this->assertSame(99, $c->compromisoId);
        $this->assertSame(7, $c->proyectoId);
        $this->assertSame($accion, $c->accion);
        $this->assertSame($sla, $c->fechaLimite);
        $this->assertNull($c->nivelEscalamientoId);
    }

    public function test_reconstituir_persiste_atributos(): void
    {
        $c = CompromisoResolucionTicket::reconstituir(
            compromisoId: 1,
            proyectoId: 2,
            accion: new AccionComprometida('Acción'),
            fechaLimite: new FechaLimiteSla(new DateTimeImmutable('+2 days')),
            nivelEscalamientoId: 4,
        );

        $this->assertSame(1, $c->compromisoId);
        $this->assertSame(4, $c->nivelEscalamientoId);
    }
}
