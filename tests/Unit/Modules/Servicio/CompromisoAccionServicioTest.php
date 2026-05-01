<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Servicio;

use App\Modules\Servicio\Domain\Entities\CompromisoAccionServicio;
use App\Modules\Servicio\Domain\ValueObjects\DescripcionAccion;
use App\Modules\Servicio\Domain\ValueObjects\FechaProgramada;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * F34C — coverage entidad CompromisoAccionServicio (P2-8 ítem 5/7).
 */
final class CompromisoAccionServicioTest extends TestCase
{
    public function test_registrar_persiste_atributos(): void
    {
        $desc = new DescripcionAccion('Cambiar router');
        $fecha = new FechaProgramada(new DateTimeImmutable('+1 day'));

        $c = CompromisoAccionServicio::registrar(
            compromisoId: 11,
            proyectoId: 5,
            descripcion: $desc,
            fechaProgramada: $fecha,
            tipoAccionServicioId: 3,
            tecnicoAsignado: 'Juan Técnico',
        );

        $this->assertSame(11, $c->compromisoId);
        $this->assertSame(5, $c->proyectoId);
        $this->assertSame($desc, $c->descripcion);
        $this->assertSame(3, $c->tipoAccionServicioId);
        $this->assertSame('Juan Técnico', $c->tecnicoAsignado);
    }

    public function test_reconstituir_admite_nulls(): void
    {
        $c = CompromisoAccionServicio::reconstituir(
            compromisoId: 1,
            proyectoId: 2,
            descripcion: new DescripcionAccion('Visita'),
            fechaProgramada: new FechaProgramada(new DateTimeImmutable('+2 hours')),
            tipoAccionServicioId: null,
            tecnicoAsignado: null,
        );

        $this->assertNull($c->tipoAccionServicioId);
        $this->assertNull($c->tecnicoAsignado);
    }
}
