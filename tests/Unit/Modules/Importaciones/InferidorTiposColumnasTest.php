<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Importaciones;

use App\Modules\CamposPersonalizados\Domain\ValueObjects\TipoCampo;
use App\Modules\Importaciones\Application\Services\InferidorTiposColumnas;
use PHPUnit\Framework\TestCase;

final class InferidorTiposColumnasTest extends TestCase
{
    private InferidorTiposColumnas $inferidor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inferidor = new InferidorTiposColumnas;
    }

    public function test_infere_booleano_con_valores_verdadero_falso(): void
    {
        $valores = ['1', '0', 'true', 'false', 'si', 'no'];

        $resultado = $this->inferidor->inferir($valores);

        self::assertSame(TipoCampo::BOOLEANO, $resultado);
    }

    public function test_infere_booleano_con_si_no_acentado(): void
    {
        $valores = ['sí', 'no', 'Sí', 'No'];

        $resultado = $this->inferidor->inferir($valores);

        self::assertSame(TipoCampo::BOOLEANO, $resultado);
    }

    public function test_infere_booleano_con_yes_no(): void
    {
        $valores = ['yes', 'no', 'Yes', 'No'];

        $resultado = $this->inferidor->inferir($valores);

        self::assertSame(TipoCampo::BOOLEANO, $resultado);
    }

    public function test_infere_fecha_hora_con_formato_iso(): void
    {
        $valores = ['2026-01-15 10:30:00', '2026-02-20 08:00:00', '2026-03-10 14:45:30'];

        $resultado = $this->inferidor->inferir($valores);

        self::assertSame(TipoCampo::FECHA_HORA, $resultado);
    }

    public function test_infere_fecha_hora_con_formato_ddmmyyyy(): void
    {
        $valores = ['15/01/2026 10:30', '20/02/2026 08:00'];

        $resultado = $this->inferidor->inferir($valores);

        self::assertSame(TipoCampo::FECHA_HORA, $resultado);
    }

    public function test_infere_fecha_con_formato_iso(): void
    {
        $valores = ['2026-01-15', '2026-02-20', '2026-03-10'];

        $resultado = $this->inferidor->inferir($valores);

        self::assertSame(TipoCampo::FECHA, $resultado);
    }

    public function test_infere_fecha_con_formato_ddmmyyyy(): void
    {
        $valores = ['15/01/2026', '20/02/2026', '10/03/2026'];

        $resultado = $this->inferidor->inferir($valores);

        self::assertSame(TipoCampo::FECHA, $resultado);
    }

    public function test_infere_fecha_con_formato_ddmmyyyy_guiones(): void
    {
        $valores = ['15-01-2026', '20-02-2026', '10-03-2026'];

        $resultado = $this->inferidor->inferir($valores);

        self::assertSame(TipoCampo::FECHA, $resultado);
    }

    public function test_infere_numero_entero_con_valores_enteros(): void
    {
        $valores = ['1000', '2500', '300', '0', '99999'];

        $resultado = $this->inferidor->inferir($valores);

        self::assertSame(TipoCampo::NUMERO_ENTERO, $resultado);
    }

    public function test_infere_numero_decimal_con_decimales(): void
    {
        $valores = ['1000.50', '2500.75', '300.00'];

        $resultado = $this->inferidor->inferir($valores);

        self::assertSame(TipoCampo::NUMERO_DECIMAL, $resultado);
    }

    public function test_infere_numero_decimal_con_coma_decimal(): void
    {
        $valores = ['1000,50', '2500,75'];

        $resultado = $this->inferidor->inferir($valores);

        self::assertSame(TipoCampo::NUMERO_DECIMAL, $resultado);
    }

    public function test_infere_seleccion_unica_con_baja_cardinalidad(): void
    {
        $valores = ['activo', 'inactivo', 'activo', 'activo', 'inactivo', 'activo', 'pendiente'];

        $resultado = $this->inferidor->inferir($valores);

        self::assertSame(TipoCampo::SELECCION_UNICA, $resultado);
    }

    public function test_no_infere_seleccion_unica_con_menos_de_5_filas(): void
    {
        $valores = ['a', 'b', 'a'];

        $resultado = $this->inferidor->inferir($valores);

        self::assertSame(TipoCampo::TEXTO_CORTO, $resultado);
    }

    public function test_no_infere_seleccion_unica_con_mas_de_8_unicos(): void
    {
        $valores = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j'];

        $resultado = $this->inferidor->inferir($valores);

        self::assertSame(TipoCampo::TEXTO_CORTO, $resultado);
    }

    public function test_infere_texto_largo_con_valor_mayor_255_chars(): void
    {
        $valores = [str_repeat('a', 300), 'texto normal'];

        $resultado = $this->inferidor->inferir($valores);

        self::assertSame(TipoCampo::TEXTO_LARGO, $resultado);
    }

    public function test_infere_texto_corto_con_texto_mixto(): void
    {
        $valores = ['Ana María', 'Pedro López', 'Juan Carlos'];

        $resultado = $this->inferidor->inferir($valores);

        self::assertSame(TipoCampo::TEXTO_CORTO, $resultado);
    }

    public function test_infere_texto_corto_con_array_vacio(): void
    {
        $resultado = $this->inferidor->inferir([]);

        self::assertSame(TipoCampo::TEXTO_CORTO, $resultado);
    }

    public function test_infere_texto_corto_con_todo_vacios(): void
    {
        $valores = ['', '  ', '', ''];

        $resultado = $this->inferidor->inferir($valores);

        self::assertSame(TipoCampo::TEXTO_CORTO, $resultado);
    }

    public function test_infere_texto_corto_con_null_como_string(): void
    {
        $valores = ['null', 'NULL', ''];

        $resultado = $this->inferidor->inferir($valores);

        self::assertSame(TipoCampo::TEXTO_CORTO, $resultado);
    }

    public function test_es_valor_vacio_con_null(): void
    {
        self::assertTrue($this->inferidor->esValorVacio(null));
    }

    public function test_es_valor_vacio_con_string_vacio(): void
    {
        self::assertTrue($this->inferidor->esValorVacio(''));
        self::assertTrue($this->inferidor->esValorVacio('  '));
    }

    public function test_es_valor_vacio_con_null_string(): void
    {
        self::assertTrue($this->inferidor->esValorVacio('null'));
        self::assertTrue($this->inferidor->esValorVacio('NULL'));
    }

    public function test_es_valor_vacio_con_valor_real(): void
    {
        self::assertFalse($this->inferidor->esValorVacio('hola'));
        self::assertFalse($this->inferidor->esValorVacio('123'));
        self::assertFalse($this->inferidor->esValorVacio('true'));
    }

    public function test_prioridad_booleano_sobre_numerico(): void
    {
        $valores = ['1', '0', '1', '0'];

        $resultado = $this->inferidor->inferir($valores);

        self::assertSame(TipoCampo::BOOLEANO, $resultado);
    }

    public function test_prioridad_fecha_hora_sobre_fecha(): void
    {
        $valores = ['2026-01-15 10:30:00', '2026-02-20 08:00:00'];

        $resultado = $this->inferidor->inferir($valores);

        self::assertSame(TipoCampo::FECHA_HORA, $resultado);
    }

    public function test_prioridad_numerico_sobre_seleccion_unica(): void
    {
        $valores = ['1', '2', '1', '2', '1', '2', '1'];

        $resultado = $this->inferidor->inferir($valores);

        self::assertSame(TipoCampo::NUMERO_ENTERO, $resultado);
    }
}
