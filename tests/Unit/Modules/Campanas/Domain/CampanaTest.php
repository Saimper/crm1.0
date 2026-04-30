<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Campanas\Domain;

use App\Modules\Campanas\Domain\Entities\Campana;
use App\Modules\Campanas\Domain\Exceptions\RangoFechasCampanaInvalido;
use App\Modules\Campanas\Domain\ValueObjects\CodigoCampana;
use App\Modules\Campanas\Domain\ValueObjects\EstadoCampana;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CampanaTest extends TestCase
{
    public function test_registra_campana_en_programada_y_normaliza_codigo(): void
    {
        $c = Campana::registrar(
            publicId: '01HXCAMP0000000000000CAMP01',
            proyectoId: 1,
            codigo: new CodigoCampana('camp-demo-abr'),
            nombre: '  Campaña demo  ',
            descripcion: null,
            fechaInicio: new DateTimeImmutable('2026-04-01'),
            fechaFin: new DateTimeImmutable('2026-04-30'),
            creadaPorId: 9,
            creadaEn: new DateTimeImmutable('2026-04-17'),
        );

        $this->assertSame('CAMP-DEMO-ABR', $c->codigo->asString());
        $this->assertSame(EstadoCampana::PROGRAMADA, $c->estado);
        $this->assertSame('Campaña demo', $c->nombre);
    }

    public function test_throws_fecha_fin_anterior_a_inicio(): void
    {
        $this->expectException(RangoFechasCampanaInvalido::class);
        Campana::registrar(
            publicId: '01HXCAMP0000000000000CAMP02',
            proyectoId: 1,
            codigo: new CodigoCampana('CAMP_X'),
            nombre: 'X',
            descripcion: null,
            fechaInicio: new DateTimeImmutable('2026-06-01'),
            fechaFin: new DateTimeImmutable('2026-05-31'),
            creadaPorId: null,
            creadaEn: new DateTimeImmutable('2026-04-17'),
        );
    }

    public function test_codigo_rechaza_caracteres_invalidos(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CodigoCampana('con espacios!');
    }
}
