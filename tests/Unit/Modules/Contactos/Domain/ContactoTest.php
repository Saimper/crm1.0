<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Contactos\Domain;

use App\Modules\Contactos\Domain\Entities\Contacto;
use App\Modules\Contactos\Domain\Exceptions\DatosContactoInvalidos;
use App\Modules\Contactos\Domain\ValueObjects\TipoContacto;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ContactoTest extends TestCase
{
    public function test_registra_contacto_telefono_normaliza_valor(): void
    {
        $contacto = Contacto::registrar(
            proyectoId:  1,
            personaId:   10,
            tipo:        TipoContacto::TELEFONO,
            valor:       '  +593 98 123 4567  ',
            etiqueta:    'móvil',
            esPrincipal: true,
            creadaEn:    new DateTimeImmutable('2026-04-17'),
        );

        $this->assertSame('+593 98 123 4567', $contacto->valor);
        $this->assertTrue($contacto->esPrincipal);
        $this->assertSame('móvil', $contacto->etiqueta);
    }

    public function test_rechaza_telefono_con_letras(): void
    {
        $this->expectException(DatosContactoInvalidos::class);
        Contacto::registrar(1, 10, TipoContacto::TELEFONO, 'hola 123', null, false, new DateTimeImmutable());
    }

    public function test_correo_valido(): void
    {
        $contacto = Contacto::registrar(
            proyectoId:  1,
            personaId:   10,
            tipo:        TipoContacto::CORREO,
            valor:       'juan.perez@correo.com',
            etiqueta:    null,
            esPrincipal: false,
            creadaEn:    new DateTimeImmutable(),
        );

        $this->assertSame('juan.perez@correo.com', $contacto->valor);
    }

    public function test_correo_invalido_throws(): void
    {
        $this->expectException(DatosContactoInvalidos::class);
        Contacto::registrar(1, 10, TipoContacto::CORREO, 'no-es-correo', null, false, new DateTimeImmutable());
    }

    public function test_direccion_muy_corta_throws(): void
    {
        $this->expectException(DatosContactoInvalidos::class);
        Contacto::registrar(1, 10, TipoContacto::DIRECCION, 'A', null, false, new DateTimeImmutable());
    }

    public function test_valor_vacio_throws(): void
    {
        $this->expectException(DatosContactoInvalidos::class);
        Contacto::registrar(1, 10, TipoContacto::TELEFONO, '   ', null, false, new DateTimeImmutable());
    }
}
