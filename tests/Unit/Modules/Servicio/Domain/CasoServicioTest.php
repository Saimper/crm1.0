<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Servicio\Domain;

use App\Modules\Servicio\Domain\Entities\CasoServicio;
use App\Modules\Servicio\Domain\Exceptions\DatosServicioInvalidos;
use App\Modules\Servicio\Domain\ValueObjects\CodigoServicio;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class CasoServicioTest extends TestCase
{
    public function test_registra_caso_servicio_valido(): void
    {
        $caso = CasoServicio::registrar(
            casoId: 1,
            proyectoId: 10,
            codigoServicio: new CodigoServicio('SVC-001'),
            tipoAccionServicioId: 2,
            estadoTecnicoId: 3,
            direccionServicio: 'Av. Amazonas N12-34',
            tecnicoAsignado: 'Juan Técnico',
            fechaSolicitud: new DateTimeImmutable('2026-04-18'),
            fechaProgramada: new DateTimeImmutable('2026-04-25 09:00:00'),
        );

        $this->assertSame('SVC-001', $caso->codigoServicio->valor);
        $this->assertSame('Juan Técnico', $caso->tecnicoAsignado);
    }

    public function test_rechaza_fecha_programada_anterior_a_solicitud(): void
    {
        $this->expectException(DatosServicioInvalidos::class);
        CasoServicio::registrar(
            casoId: 1,
            proyectoId: 10,
            codigoServicio: new CodigoServicio('SVC-002'),
            tipoAccionServicioId: null,
            estadoTecnicoId: null,
            direccionServicio: null,
            tecnicoAsignado: null,
            fechaSolicitud: new DateTimeImmutable('2026-04-18'),
            fechaProgramada: new DateTimeImmutable('2026-04-10'),
        );
    }

    public function test_rechaza_direccion_excesiva(): void
    {
        $this->expectException(DatosServicioInvalidos::class);
        CasoServicio::registrar(
            casoId: 1,
            proyectoId: 10,
            codigoServicio: new CodigoServicio('SVC-003'),
            tipoAccionServicioId: null,
            estadoTecnicoId: null,
            direccionServicio: str_repeat('a', 501),
            tecnicoAsignado: null,
            fechaSolicitud: new DateTimeImmutable('2026-04-18'),
            fechaProgramada: null,
        );
    }

    public function test_codigo_servicio_vacio_rechazado(): void
    {
        $this->expectException(DatosServicioInvalidos::class);
        new CodigoServicio('   ');
    }
}
