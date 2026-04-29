<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Tenancy\Domain;

use App\Modules\Tenancy\Domain\Entities\Mandante;
use App\Modules\Tenancy\Domain\ValueObjects\CodigoMandante;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MandanteTest extends TestCase
{
    public function test_registra_mandante_activo_con_codigo_normalizado_a_mayusculas(): void
    {
        $mandante = Mandante::registrar(
            publicId:  '01HXTENANCY0000000MANDANTE1',
            codigo:    new CodigoMandante('bpo_demo'),
            nombre:    '  BPO Demo Corp  ',
            documento: '0000000000001',
            creadaEn:  new DateTimeImmutable('2026-04-17'),
        );

        $this->assertNull($mandante->id);
        $this->assertSame('BPO_DEMO', $mandante->codigo->asString());
        $this->assertSame('BPO Demo Corp', $mandante->nombre);
        $this->assertTrue($mandante->activo);
    }

    public function test_codigo_mandante_rechaza_longitud_invalida(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CodigoMandante('A');
    }

    public function test_codigo_mandante_rechaza_caracteres_invalidos(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CodigoMandante('NO PERMITIDO');
    }

    public function test_desactivar_preserva_codigo_y_cambia_estado(): void
    {
        $mandante = Mandante::registrar(
            publicId:  '01HXTENANCY0000000MANDANTE2',
            codigo:    new CodigoMandante('BPO_TEST'),
            nombre:    'BPO Test',
            documento: null,
            creadaEn:  new DateTimeImmutable('2026-04-17'),
        );

        $desactivado = $mandante->desactivar();

        $this->assertFalse($desactivado->activo);
        $this->assertSame('BPO_TEST', $desactivado->codigo->asString());
    }
}
