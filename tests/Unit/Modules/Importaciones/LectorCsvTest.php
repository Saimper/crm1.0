<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Importaciones;

use App\Modules\Importaciones\Application\Services\LectorCsv;
use PHPUnit\Framework\TestCase;

final class LectorCsvTest extends TestCase
{
    public function test_lee_headers_simples(): void
    {
        $csv = "ced,nombre,apellido\n100,Ana,Diaz\n200,Luis,Paz\n";

        $headers = (new LectorCsv)->leerHeaders($csv);
        $this->assertSame(['ced', 'nombre', 'apellido'], $headers);
    }

    public function test_strip_bom_utf8(): void
    {
        $csv = "\xEF\xBB\xBFced,nombre\n100,Ana\n";

        $headers = (new LectorCsv)->leerHeaders($csv);
        $this->assertSame(['ced', 'nombre'], $headers);
    }

    public function test_lineas_vacias_ignoradas(): void
    {
        $csv = "a,b\n\n1,2\n\n3,4\n";

        $filas = (new LectorCsv)->leerFilas($csv);
        $this->assertSame([['1', '2'], ['3', '4']], $filas);
    }

    public function test_headers_duplicados_se_desambiguan(): void
    {
        $csv = "ced,ced,nombre\n100,200,Ana\n";

        $headers = (new LectorCsv)->leerHeaders($csv);
        $this->assertSame(['ced', 'ced_2', 'nombre'], $headers);
    }

    public function test_valores_con_comillas_y_comas(): void
    {
        $csv = "asunto,nota\n\"Hola, mundo\",\"con comilla\"\"interna\"\n";

        $filas = (new LectorCsv)->leerFilas($csv);
        $this->assertSame([['Hola, mundo', 'con comilla"interna']], $filas);
    }

    public function test_csv_solo_headers_filas_vacias(): void
    {
        $csv = "a,b,c\n";

        $this->assertSame([], (new LectorCsv)->leerFilas($csv));
        $this->assertSame(0, (new LectorCsv)->contarFilas($csv));
    }

    public function test_limit_corta_lectura(): void
    {
        $csv = "a\n1\n2\n3\n4\n5\n";

        $filas = (new LectorCsv)->leerFilas($csv, 3);
        $this->assertCount(3, $filas);
        $this->assertSame([['1'], ['2'], ['3']], $filas);
    }

    public function test_contar_filas_no_cuenta_header(): void
    {
        $csv = "a,b\n1,2\n3,4\n5,6\n";
        $this->assertSame(3, (new LectorCsv)->contarFilas($csv));
    }
}
