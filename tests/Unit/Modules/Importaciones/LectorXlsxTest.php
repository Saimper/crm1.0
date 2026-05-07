<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Importaciones;

use App\Modules\Importaciones\Application\Services\LectorXlsx;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use PHPUnit\Framework\TestCase;

final class LectorXlsxTest extends TestCase
{
    private string $path;

    protected function tearDown(): void
    {
        if (isset($this->path) && file_exists($this->path)) {
            @unlink($this->path);
        }
    }

    public function test_lee_headers_simples(): void
    {
        $this->path = $this->escribirXlsx([
            ['ced', 'nombre', 'apellido'],
            ['100', 'Ana', 'Diaz'],
        ]);

        $headers = (new LectorXlsx)->leerHeaders($this->path);
        $this->assertSame(['ced', 'nombre', 'apellido'], $headers);
    }

    public function test_filas_se_leen_como_strings(): void
    {
        $this->path = $this->escribirXlsx([
            ['a', 'b'],
            ['1', 'dos'],
            ['3', 'cuatro'],
        ]);

        $filas = (new LectorXlsx)->leerFilas($this->path);
        $this->assertSame([['1', 'dos'], ['3', 'cuatro']], $filas);
    }

    public function test_contar_filas_no_cuenta_header(): void
    {
        $this->path = $this->escribirXlsx([
            ['h'],
            ['1'],
            ['2'],
            ['3'],
        ]);

        $this->assertSame(3, (new LectorXlsx)->contarFilas($this->path));
    }

    public function test_limit_corta_lectura(): void
    {
        $this->path = $this->escribirXlsx([
            ['h'],
            ['1'],
            ['2'],
            ['3'],
            ['4'],
        ]);

        $this->assertCount(2, (new LectorXlsx)->leerFilas($this->path, 2));
    }

    public function test_headers_duplicados_se_desambiguan(): void
    {
        $this->path = $this->escribirXlsx([
            ['ced', 'ced', 'nom'],
            ['1', '2', 'Ana'],
        ]);

        $this->assertSame(['ced', 'ced_2', 'nom'], (new LectorXlsx)->leerHeaders($this->path));
    }

    /** @param list<list<string>> $filas */
    private function escribirXlsx(array $filas): string
    {
        $path = tempnam(sys_get_temp_dir(), 'imp_').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        foreach ($filas as $fila) {
            $writer->addRow(Row::fromValues($fila));
        }
        $writer->close();

        return $path;
    }
}
