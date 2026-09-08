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

    /**
     * Un CSV guardado por Excel «sin Unicode» viene entero en Windows-1252; otro,
     * ya en UTF-8, puede traer celdas con la doble codificación con la que lo
     * exportó el sistema del cliente. Un archivo es una cosa o la otra, y las
     * dos se corrigen al leer.
     */
    public function test_convierte_windows_1252_y_repara_la_doble_codificacion_al_leer(): void
    {
        $lector = new LectorCsv;

        $latin1 = "ced,apellido\n100,PE\xD1A\n";
        $this->assertSame(['ced', 'apellido'], $lector->leerHeaders($latin1));
        $this->assertSame(1, $lector->contarFilas($latin1));
        $this->assertSame('PEÑA', $lector->leerFilas($latin1)[0][1]);

        $dobleCodificado = "ced,Direcci\xC3\x83\xC2\xB3n\n200,GONZ\xC3\x83\xC2\x81LEZ\n";
        $this->assertSame(['ced', 'Dirección'], $lector->leerHeaders($dobleCodificado));
        $this->assertSame('GONZÁLEZ', $lector->leerFilas($dobleCodificado)[0][1]);
    }

    /**
     * El viaje de vuelta de las filas rechazadas.
     *
     * El CRM las descarga con una comilla delante de lo que Excel leería como
     * fórmula. El supervisor corrige el archivo y lo vuelve a subir; sin
     * deshacer esa comilla, un teléfono «+50761750650» entraría como
     * «'+50761750650» y ya no casaría con nada.
     */
    public function test_deshace_la_comilla_que_neutraliza_formulas(): void
    {
        $lector = new LectorCsv;

        $csv = "ced,telefono,nota,apostrofo\n"
            ."100,'+50761750650,'-descuento,'ojo\n";

        $this->assertSame(['ced', 'telefono', 'nota', 'apostrofo'], $lector->leerHeaders($csv));

        $fila = $lector->leerFilas($csv)[0];
        $this->assertSame('+50761750650', $fila[1]);
        $this->assertSame('-descuento', $fila[2]);
        $this->assertSame("'ojo", $fila[3], 'Una comilla que no neutraliza nada es texto y se respeta.');
    }
}
