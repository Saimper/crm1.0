<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Tenancy\Domain;

use App\Modules\Tenancy\Domain\Entities\Proyecto;
use App\Modules\Tenancy\Domain\Exceptions\RangoFechasProyectoInvalido;
use App\Modules\Tenancy\Domain\ValueObjects\CodigoProyecto;
use App\Modules\Tenancy\Domain\ValueObjects\TipoOperacion;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ProyectoTest extends TestCase
{
    public function test_registra_proyecto_con_tipo_operacion_y_rango_valido(): void
    {
        $proyecto = Proyecto::registrar(
            publicId:      '01HXTENANCY0000000PROY0001',
            mandanteId:    42,
            codigo:        new CodigoProyecto('COBRANZA_DEMO_2026'),
            nombre:        'Cobranza Demo 2026',
            descripcion:   'Proyecto demo',
            tipoOperacion: TipoOperacion::COBRANZA,
            fechaInicio:   new DateTimeImmutable('2026-04-01'),
            fechaFin:      new DateTimeImmutable('2026-12-31'),
            creadaEn:      new DateTimeImmutable('2026-04-17'),
        );

        $this->assertSame(TipoOperacion::COBRANZA, $proyecto->tipoOperacion);
        $this->assertSame(42, $proyecto->mandanteId);
    }

    public function test_throws_cuando_fecha_fin_es_anterior_a_inicio(): void
    {
        $this->expectException(RangoFechasProyectoInvalido::class);

        Proyecto::registrar(
            publicId:      '01HXTENANCY0000000PROY0002',
            mandanteId:    42,
            codigo:        new CodigoProyecto('FECHAS_INVALIDAS'),
            nombre:        'Fechas mal',
            descripcion:   null,
            tipoOperacion: TipoOperacion::VENTA,
            fechaInicio:   new DateTimeImmutable('2026-06-01'),
            fechaFin:      new DateTimeImmutable('2026-05-31'),
            creadaEn:      new DateTimeImmutable('2026-04-17'),
        );
    }

    public function test_acepta_proyecto_sin_fechas(): void
    {
        $proyecto = Proyecto::registrar(
            publicId:      '01HXTENANCY0000000PROY0003',
            mandanteId:    42,
            codigo:        new CodigoProyecto('SIN_FECHA'),
            nombre:        'Sin fecha',
            descripcion:   null,
            tipoOperacion: TipoOperacion::CX,
            fechaInicio:   null,
            fechaFin:      null,
            creadaEn:      new DateTimeImmutable('2026-04-17'),
        );

        $this->assertNull($proyecto->fechaInicio);
        $this->assertNull($proyecto->fechaFin);
    }
}
