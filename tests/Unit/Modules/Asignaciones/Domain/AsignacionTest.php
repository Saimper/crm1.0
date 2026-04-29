<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Asignaciones\Domain;

use App\Modules\Asignaciones\Domain\Entities\Asignacion;
use App\Modules\Asignaciones\Domain\Exceptions\TransicionAsignacionInvalida;
use App\Modules\Asignaciones\Domain\ValueObjects\EstadoAsignacion;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class AsignacionTest extends TestCase
{
    public function test_registra_asignacion_pendiente(): void
    {
        $a = $this->base();

        $this->assertSame(EstadoAsignacion::PENDIENTE, $a->estado);
        $this->assertNull($a->cerradaEn);
    }

    public function test_rechaza_prioridad_negativa(): void
    {
        $this->expectException(TransicionAsignacionInvalida::class);
        Asignacion::registrar(
            publicId:        '01HXASIG0000000000000ASIG01',
            proyectoId:      1,
            campanaId:       2,
            casoId:          3,
            usuarioId:       4,
            fechaAsignacion: new DateTimeImmutable('2026-04-17'),
            prioridad:       -1,
            creadaEn:        new DateTimeImmutable('2026-04-17'),
        );
    }

    public function test_cerrar_cambia_estado(): void
    {
        $cerrada = $this->base()->cerrar(new DateTimeImmutable('2026-04-20'));

        $this->assertSame(EstadoAsignacion::CERRADA, $cerrada->estado);
        $this->assertNotNull($cerrada->cerradaEn);
    }

    public function test_no_puede_cerrarse_dos_veces(): void
    {
        $cerrada = $this->base()->cerrar(new DateTimeImmutable('2026-04-20'));

        $this->expectException(TransicionAsignacionInvalida::class);
        $cerrada->cerrar(new DateTimeImmutable('2026-04-21'));
    }

    private function base(): Asignacion
    {
        return Asignacion::registrar(
            publicId:        '01HXASIG0000000000000ASIG02',
            proyectoId:      1,
            campanaId:       2,
            casoId:          3,
            usuarioId:       4,
            fechaAsignacion: new DateTimeImmutable('2026-04-17'),
            prioridad:       100,
            creadaEn:        new DateTimeImmutable('2026-04-17'),
        );
    }
}
