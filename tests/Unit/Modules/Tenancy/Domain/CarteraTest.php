<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Tenancy\Domain;

use App\Modules\Tenancy\Domain\Entities\Cartera;
use App\Modules\Tenancy\Domain\ValueObjects\CodigoCartera;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CarteraTest extends TestCase
{
    public function test_registra_cartera_activa_y_normaliza_nombre(): void
    {
        $cartera = Cartera::registrar(
            publicId:    '01HXTENANCY0000000CART0001',
            proyectoId:  7,
            codigo:      new CodigoCartera('CONSUMO'),
            nombre:      '  Consumo  ',
            descripcion: null,
            creadaEn:    new DateTimeImmutable('2026-04-17'),
        );

        $this->assertSame('CONSUMO', $cartera->codigo->asString());
        $this->assertSame('Consumo', $cartera->nombre);
        $this->assertTrue($cartera->activo);
    }

    public function test_codigo_cartera_rechaza_caracteres_invalidos(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CodigoCartera('cartera con espacios!');
    }

    public function test_con_id_produce_cartera_persistida(): void
    {
        $cartera = Cartera::registrar(
            publicId:    '01HXTENANCY0000000CART0002',
            proyectoId:  7,
            codigo:      new CodigoCartera('MICRO'),
            nombre:      'Microempresa',
            descripcion: null,
            creadaEn:    new DateTimeImmutable('2026-04-17'),
        );

        $persistida = $cartera->conId(99);

        $this->assertSame(99, $persistida->id);
        $this->assertSame($cartera->publicId, $persistida->publicId);
    }
}
