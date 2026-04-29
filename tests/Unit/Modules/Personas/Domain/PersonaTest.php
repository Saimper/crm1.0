<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Personas\Domain;

use App\Modules\Personas\Domain\Entities\Persona;
use App\Modules\Personas\Domain\Exceptions\DatosPersonaInvalidos;
use App\Modules\Personas\Domain\ValueObjects\Identificacion;
use App\Modules\Personas\Domain\ValueObjects\TipoPersona;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PersonaTest extends TestCase
{
    public function test_crea_persona_fisica_con_nombres_y_apellidos(): void
    {
        $persona = Persona::registrar(
            publicId:             '01HXPERSONA000000000000001',
            proyectoId:           10,
            tipoPersona:          TipoPersona::FISICA,
            tipoIdentificacionId: 1,
            identificacion:       new Identificacion('0102030405'),
            nombres:              'Juan',
            apellidos:            'Pérez',
            razonSocial:          null,
            fechaNacimiento:      null,
            creadaEn:             new DateTimeImmutable('2026-04-17'),
        );

        $this->assertNull($persona->id);
        $this->assertSame(10, $persona->proyectoId);
        $this->assertSame('Juan Pérez', $persona->nombreCompleto());
        $this->assertNull($persona->razonSocial);
    }

    public function test_throws_cuando_persona_fisica_no_tiene_nombres(): void
    {
        $this->expectException(DatosPersonaInvalidos::class);
        Persona::registrar(
            publicId:             '01HXPERSONA000000000000002',
            proyectoId:           10,
            tipoPersona:          TipoPersona::FISICA,
            tipoIdentificacionId: 1,
            identificacion:       new Identificacion('0102030405'),
            nombres:              '  ',
            apellidos:            'Pérez',
            razonSocial:          null,
            fechaNacimiento:      null,
            creadaEn:             new DateTimeImmutable('2026-04-17'),
        );
    }

    public function test_crea_persona_juridica_descarta_nombres_y_fecha_nacimiento(): void
    {
        $persona = Persona::registrar(
            publicId:             '01HXPERSONA000000000000003',
            proyectoId:           10,
            tipoPersona:          TipoPersona::JURIDICA,
            tipoIdentificacionId: 2,
            identificacion:       new Identificacion('1792345678001'),
            nombres:              'Ignorado',
            apellidos:            'Ignorado',
            razonSocial:          'Comercial Austral S.A.',
            fechaNacimiento:      new DateTimeImmutable('1980-01-01'),
            creadaEn:             new DateTimeImmutable('2026-04-17'),
        );

        $this->assertSame('Comercial Austral S.A.', $persona->nombreCompleto());
        $this->assertNull($persona->nombres);
        $this->assertNull($persona->apellidos);
        $this->assertNull($persona->fechaNacimiento);
    }

    public function test_throws_cuando_persona_juridica_no_tiene_razon_social(): void
    {
        $this->expectException(DatosPersonaInvalidos::class);
        Persona::registrar(
            publicId:             '01HXPERSONA000000000000004',
            proyectoId:           10,
            tipoPersona:          TipoPersona::JURIDICA,
            tipoIdentificacionId: 2,
            identificacion:       new Identificacion('1792345678001'),
            nombres:              null,
            apellidos:            null,
            razonSocial:          null,
            fechaNacimiento:      null,
            creadaEn:             new DateTimeImmutable('2026-04-17'),
        );
    }

    public function test_identificacion_rechaza_formato_invalido(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Identificacion('ab@!$');
    }

    public function test_con_id_produce_persona_persistida_con_mismo_proyecto(): void
    {
        $persona = Persona::registrar(
            publicId:             '01HXPERSONA000000000000005',
            proyectoId:           7,
            tipoPersona:          TipoPersona::FISICA,
            tipoIdentificacionId: 1,
            identificacion:       new Identificacion('0304050607'),
            nombres:              'Carlos',
            apellidos:            'Ramírez',
            razonSocial:          null,
            fechaNacimiento:      null,
            creadaEn:             new DateTimeImmutable('2026-04-17'),
        );

        $persistida = $persona->conId(42);

        $this->assertSame(42, $persistida->id);
        $this->assertSame(7, $persistida->proyectoId);
        $this->assertSame($persona->publicId, $persistida->publicId);
    }
}
