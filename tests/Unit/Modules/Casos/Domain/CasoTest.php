<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Casos\Domain;

use App\Modules\Casos\Domain\Entities\Caso;
use App\Modules\Casos\Domain\Exceptions\TransicionCasoInvalida;
use App\Modules\Casos\Domain\ValueObjects\TipoCaso;
use App\Modules\Tenancy\Domain\ValueObjects\TipoOperacion;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class CasoTest extends TestCase
{
    public function test_tipo_caso_se_deriva_de_tipo_operacion(): void
    {
        $this->assertSame(TipoCaso::COBRANZA,   TipoCaso::desdeOperacion(TipoOperacion::COBRANZA));
        $this->assertSame(TipoCaso::TICKET_CX,  TipoCaso::desdeOperacion(TipoOperacion::CX));
        $this->assertSame(TipoCaso::LEAD_VENTA, TipoCaso::desdeOperacion(TipoOperacion::VENTA));
        $this->assertSame(TipoCaso::SERVICIO,   TipoCaso::desdeOperacion(TipoOperacion::SERVICIO));
    }

    public function test_registra_caso_abierto(): void
    {
        $caso = Caso::registrar(
            publicId:     '01HXCASO0000000000000CASO01',
            proyectoId:   1,
            carteraId:    2,
            personaId:    3,
            tipoCaso:     TipoCaso::COBRANZA,
            estadoCasoId: 5,
            fechaIngreso: new DateTimeImmutable('2026-04-17'),
            prioridad:    100,
            creadaEn:     new DateTimeImmutable('2026-04-17'),
        );

        $this->assertFalse($caso->estaCerrado());
        $this->assertNull($caso->id);
        $this->assertSame(100, $caso->prioridad);
    }

    public function test_rechaza_prioridad_negativa(): void
    {
        $this->expectException(TransicionCasoInvalida::class);
        Caso::registrar(
            publicId:     '01HXCASO0000000000000CASO02',
            proyectoId:   1,
            carteraId:    2,
            personaId:    3,
            tipoCaso:     TipoCaso::COBRANZA,
            estadoCasoId: 5,
            fechaIngreso: new DateTimeImmutable('2026-04-17'),
            prioridad:    -1,
            creadaEn:     new DateTimeImmutable('2026-04-17'),
        );
    }

    public function test_cerrar_cambia_estado_y_fija_cerrado_en(): void
    {
        $caso = $this->casoBase();
        $cerrado = $caso->cerrar(nuevoEstadoCasoId: 9, cerradoEn: new DateTimeImmutable('2026-05-01 10:00:00'));

        $this->assertTrue($cerrado->estaCerrado());
        $this->assertSame(9, $cerrado->estadoCasoId);
    }

    public function test_no_puede_cerrarse_dos_veces(): void
    {
        $caso = $this->casoBase();
        $cerrado = $caso->cerrar(9, new DateTimeImmutable('2026-05-01'));

        $this->expectException(TransicionCasoInvalida::class);
        $cerrado->cerrar(9, new DateTimeImmutable('2026-05-02'));
    }

    private function casoBase(): Caso
    {
        return Caso::registrar(
            publicId:     '01HXCASO0000000000000CASO03',
            proyectoId:   1,
            carteraId:    2,
            personaId:    3,
            tipoCaso:     TipoCaso::COBRANZA,
            estadoCasoId: 5,
            fechaIngreso: new DateTimeImmutable('2026-04-17'),
            prioridad:    100,
            creadaEn:     new DateTimeImmutable('2026-04-17'),
        );
    }
}
