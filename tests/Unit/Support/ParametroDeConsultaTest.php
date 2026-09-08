<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Http\ParametroDeConsulta;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

final class ParametroDeConsultaTest extends TestCase
{
    public function test_un_array_en_la_query_string_cuenta_como_sin_filtro(): void
    {
        $request = Request::create('/x', 'GET', ['q' => ['a'], 'cartera' => [1], 'desde' => ['2026-01-01']]);

        self::assertSame('', ParametroDeConsulta::texto($request, 'q'));
        self::assertNull(ParametroDeConsulta::entero($request, 'cartera'));
        self::assertNull(ParametroDeConsulta::fecha($request, 'desde'));
    }

    public function test_un_id_son_solo_digitos(): void
    {
        $request = Request::create('/x', 'GET', ['cartera' => ' 12 ', 'estado' => '12abc', 'otro' => '-1']);

        self::assertSame(12, ParametroDeConsulta::entero($request, 'cartera'));
        self::assertNull(ParametroDeConsulta::entero($request, 'estado'));
        self::assertNull(ParametroDeConsulta::entero($request, 'otro'));
        self::assertNull(ParametroDeConsulta::entero($request, 'ausente'));
    }

    public function test_una_fecha_tiene_que_ser_de_calendario(): void
    {
        $request = Request::create('/x', 'GET', ['a' => '2026-02-30', 'b' => '2026-09-08', 'c' => '08/09/2026']);

        self::assertNull(ParametroDeConsulta::fecha($request, 'a'));
        self::assertSame('2026-09-08', ParametroDeConsulta::fecha($request, 'b'));
        self::assertNull(ParametroDeConsulta::fecha($request, 'c'));
    }
}
