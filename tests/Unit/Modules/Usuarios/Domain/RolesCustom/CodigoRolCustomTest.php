<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Usuarios\Domain\RolesCustom;

use App\Modules\Usuarios\Domain\RolesCustom\ValueObjects\CodigoRolCustom;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CodigoRolCustomTest extends TestCase
{
    public function test_acepta_codigo_valido_mayusculas_y_underscore(): void
    {
        $vo = new CodigoRolCustom('GESTOR_TELEVENTAS');
        $this->assertSame('GESTOR_TELEVENTAS', $vo->asString());
    }

    public function test_normaliza_a_mayusculas(): void
    {
        $vo = new CodigoRolCustom('gestor_televentas');
        $this->assertSame('GESTOR_TELEVENTAS', $vo->asString());
    }

    public function test_recorta_espacios(): void
    {
        $vo = new CodigoRolCustom('  CUSTOM_X  ');
        $this->assertSame('CUSTOM_X', $vo->asString());
    }

    public function test_rechaza_codigo_que_empieza_con_numero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CodigoRolCustom('1ROL');
    }

    public function test_rechaza_codigo_con_caracteres_invalidos(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CodigoRolCustom('ROL-INVALIDO');
    }

    public function test_rechaza_codigo_demasiado_corto(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CodigoRolCustom('A');
    }

    public function test_rechaza_codigo_demasiado_largo(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CodigoRolCustom(str_repeat('A', 41));
    }
}
