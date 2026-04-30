<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Clientes\Domain;

use App\Modules\Clientes\Domain\Entities\Cliente;
use App\Modules\Clientes\Domain\Exceptions\DatosClienteInvalidos;
use App\Modules\Clientes\Domain\ValueObjects\Identificacion;
use App\Modules\Clientes\Domain\ValueObjects\TipoPersona;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ClienteTest extends TestCase
{
    public function test_crea_persona_fisica_con_nombres_y_apellidos(): void
    {
        $cliente = Cliente::registrar(
            publicId: '01HXTEST0000000000000CLI01',
            tipoPersona: TipoPersona::FISICA,
            tipoIdentificacionId: 1,
            identificacion: new Identificacion('0102030405'),
            nombres: 'Juan',
            apellidos: 'Pérez',
            razonSocial: null,
            fechaNacimiento: null,
            creadaEn: new DateTimeImmutable('2026-04-17'),
        );

        $this->assertNull($cliente->id);
        $this->assertSame('Juan Pérez', $cliente->nombreCompleto());
        $this->assertNull($cliente->razonSocial);
    }

    public function test_throws_cuando_persona_fisica_no_tiene_nombres(): void
    {
        $this->expectException(DatosClienteInvalidos::class);
        Cliente::registrar(
            publicId: '01HXTEST0000000000000CLI02',
            tipoPersona: TipoPersona::FISICA,
            tipoIdentificacionId: 1,
            identificacion: new Identificacion('0102030405'),
            nombres: '   ',
            apellidos: 'Pérez',
            razonSocial: null,
            fechaNacimiento: null,
            creadaEn: new DateTimeImmutable('2026-04-17'),
        );
    }

    public function test_crea_persona_juridica_con_razon_social(): void
    {
        $cliente = Cliente::registrar(
            publicId: '01HXTEST0000000000000CLI03',
            tipoPersona: TipoPersona::JURIDICA,
            tipoIdentificacionId: 2,
            identificacion: new Identificacion('1792345678001'),
            nombres: 'Ignorado',
            apellidos: 'Ignorado',
            razonSocial: 'Comercial Austral S.A.',
            fechaNacimiento: new DateTimeImmutable('1980-01-01'),
            creadaEn: new DateTimeImmutable('2026-04-17'),
        );

        $this->assertSame('Comercial Austral S.A.', $cliente->nombreCompleto());
        $this->assertNull($cliente->nombres, 'Los nombres se deben descartar al registrar una persona jurídica.');
        $this->assertNull($cliente->apellidos);
        $this->assertNull($cliente->fechaNacimiento, 'La fecha de nacimiento se debe descartar para personas jurídicas.');
    }

    public function test_throws_cuando_persona_juridica_no_tiene_razon_social(): void
    {
        $this->expectException(DatosClienteInvalidos::class);
        Cliente::registrar(
            publicId: '01HXTEST0000000000000CLI04',
            tipoPersona: TipoPersona::JURIDICA,
            tipoIdentificacionId: 2,
            identificacion: new Identificacion('1792345678001'),
            nombres: null,
            apellidos: null,
            razonSocial: null,
            fechaNacimiento: null,
            creadaEn: new DateTimeImmutable('2026-04-17'),
        );
    }

    public function test_identificacion_rechaza_formato_invalido(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Identificacion('ab!$%');
    }

    public function test_con_id_produce_cliente_persistido(): void
    {
        $cliente = Cliente::registrar(
            publicId: '01HXTEST0000000000000CLI05',
            tipoPersona: TipoPersona::FISICA,
            tipoIdentificacionId: 1,
            identificacion: new Identificacion('0304050607'),
            nombres: 'Carlos',
            apellidos: 'Ramírez',
            razonSocial: null,
            fechaNacimiento: null,
            creadaEn: new DateTimeImmutable('2026-04-17'),
        );

        $persistido = $cliente->conId(42);
        $this->assertSame(42, $persistido->id);
        $this->assertSame($cliente->publicId, $persistido->publicId);
    }
}
