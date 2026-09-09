<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Csv\RespuestaCsv;
use PHPUnit\Framework\TestCase;

final class RespuestaCsvTest extends TestCase
{
    public function test_una_celda_que_excel_leeria_como_formula_sale_como_texto(): void
    {
        self::assertSame("'=HYPERLINK(\"http://x\")", RespuestaCsv::celda('=HYPERLINK("http://x")'));
        self::assertSame("'+1+1", RespuestaCsv::celda('+1+1'));
        self::assertSame("'@SUM(A1)", RespuestaCsv::celda('@SUM(A1)'));
        self::assertSame("'-1+1", RespuestaCsv::celda('-1+1'));
        self::assertSame("'\tcmd", RespuestaCsv::celda("\tcmd"));
    }

    public function test_un_importe_negativo_no_es_una_formula(): void
    {
        self::assertSame('-120.50', RespuestaCsv::celda('-120.50'));
        self::assertSame('-3', RespuestaCsv::celda(-3));
    }

    public function test_los_tipos_basicos_se_escriben_como_los_lee_una_persona(): void
    {
        self::assertSame('', RespuestaCsv::celda(null));
        self::assertSame('sí', RespuestaCsv::celda(true));
        self::assertSame('no', RespuestaCsv::celda(false));
        self::assertSame('2026-09-08 10:30:00', RespuestaCsv::celda(new \DateTimeImmutable('2026-09-08 10:30:00')));
        self::assertSame('Pérez', RespuestaCsv::celda('Pérez'));
    }

    public function test_el_nombre_del_fichero_no_arrastra_lo_que_venga_de_la_base(): void
    {
        self::assertSame('casos_BAZ_TECA_20260908.csv', RespuestaCsv::nombreSeguro('casos_BAZ TECA_20260908.csv'));
        self::assertSame('a__b.csv', RespuestaCsv::nombreSeguro("a\"\nb.csv"));
        self::assertSame('descarga.csv', RespuestaCsv::nombreSeguro(''));
    }
}
