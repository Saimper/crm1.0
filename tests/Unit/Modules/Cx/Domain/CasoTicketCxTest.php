<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Cx\Domain;

use App\Modules\Cx\Domain\Entities\CasoTicketCx;
use App\Modules\Cx\Domain\Exceptions\DatosTicketInvalidos;
use App\Modules\Cx\Domain\ValueObjects\AsuntoTicket;
use App\Modules\Cx\Domain\ValueObjects\CodigoTicket;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class CasoTicketCxTest extends TestCase
{
    public function test_registra_ticket_valido(): void
    {
        $ticket = CasoTicketCx::registrar(
            casoId:              1,
            proyectoId:          10,
            codigoTicket:        new CodigoTicket('TKT-001'),
            asunto:              new AsuntoTicket('Problema con activación'),
            descripcion:         'Cliente reporta error al activar.',
            categoriaTicketId:   2,
            prioridadTicketId:   3,
            nivelSlaId:          1,
            nivelEscalamientoId: 1,
            fechaReporte:        new DateTimeImmutable('2026-04-18 09:00:00'),
            fechaLimiteSla:      new DateTimeImmutable('2026-04-19 17:00:00'),
        );

        $this->assertSame('TKT-001', $ticket->codigoTicket->valor);
        $this->assertSame('Problema con activación', $ticket->asunto->valor);
    }

    public function test_rechaza_fecha_sla_anterior_a_reporte(): void
    {
        $this->expectException(DatosTicketInvalidos::class);
        CasoTicketCx::registrar(
            casoId:              1,
            proyectoId:          10,
            codigoTicket:        new CodigoTicket('TKT-002'),
            asunto:              new AsuntoTicket('Test'),
            descripcion:         null,
            categoriaTicketId:   null,
            prioridadTicketId:   null,
            nivelSlaId:          null,
            nivelEscalamientoId: null,
            fechaReporte:        new DateTimeImmutable('2026-04-18 09:00:00'),
            fechaLimiteSla:      new DateTimeImmutable('2026-04-17 17:00:00'),
        );
    }

    public function test_codigo_ticket_vacio_rechazado(): void
    {
        $this->expectException(DatosTicketInvalidos::class);
        new CodigoTicket('   ');
    }

    public function test_asunto_vacio_rechazado(): void
    {
        $this->expectException(DatosTicketInvalidos::class);
        new AsuntoTicket('');
    }
}
