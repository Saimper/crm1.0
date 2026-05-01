<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\CamposPersonalizados;

use App\Modules\CamposPersonalizados\Domain\ValueObjects\CodigoCampo;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * F34C — coverage VO CodigoCampo (P2-8 ítem 1/7).
 */
final class CodigoCampoTest extends TestCase
{
    public function test_normaliza_a_minusculas_y_trim(): void
    {
        $vo = new CodigoCampo('  Mi_Campo  ');
        $this->assertSame('mi_campo', $vo->asString());
    }

    public function test_acepta_letras_digitos_y_guion_bajo(): void
    {
        $vo = new CodigoCampo('campo_42');
        $this->assertSame('campo_42', $vo->asString());
    }

    public function test_rechaza_corto(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CodigoCampo('a');
    }

    public function test_rechaza_largo(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CodigoCampo(str_repeat('x', 81));
    }

    public function test_rechaza_empieza_por_digito(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CodigoCampo('1campo');
    }

    public function test_rechaza_caracter_invalido(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CodigoCampo('mi-campo');
    }
}
