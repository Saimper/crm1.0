<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use App\Support\Csv\RespuestaCsv;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class RespuestaCsvDesdeConsultaTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_pagina_por_clave_y_escribe_todas_las_filas_en_orden(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        for ($i = 1; $i <= 7; $i++) {
            $this->crearPersonaEn($proyecto, '900000'.$i);
        }

        $consulta = DB::table('personas as p')
            ->where('p.proyecto_id', $proyecto->id)
            ->orderByDesc('p.creada_en') // se ignora: se pagina por id
            ->select(['p.id', 'p.identificacion']);

        $lotes = 0;
        $total = null;

        $respuesta = RespuestaCsv::desdeConsulta(
            'personas.csv',
            ['identificacion', 'nota'],
            $consulta,
            'p.id',
            'id',
            fn (object $p, mixed $ctx): array => [$p->identificacion, $ctx],
            function (Collection $lote) use (&$lotes): string {
                $lotes++;

                return 'lote'.$lotes;
            },
            function (int $n) use (&$total): void {
                $total = $n;
            },
            tamanoLote: 3,
        );

        ob_start();
        $respuesta->sendContent();
        $csv = (string) ob_get_clean();

        self::assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $lineas = array_values(array_filter(explode("\n", trim(substr($csv, 3)))));
        self::assertSame('identificacion,nota', $lineas[0]);
        self::assertCount(8, $lineas);
        self::assertSame('9000001,lote1', $lineas[1]);
        self::assertSame('9000007,lote3', $lineas[7]);
        self::assertSame(3, $lotes);
        self::assertSame(7, $total);
        self::assertSame('attachment; filename="personas.csv"', $respuesta->headers->get('Content-Disposition'));
        self::assertSame('no', $respuesta->headers->get('X-Accel-Buffering'));
    }

    /**
     * Las cabeceras también se neutralizan.
     *
     * No son constantes: en la descarga de filas rechazadas son los nombres de
     * las columnas del archivo que subió el cliente, y en las exportaciones de
     * casos son las etiquetas de sus campos personalizados. Una columna
     * llamada «=cmd|'/c calc'!A1» abriría la calculadora al abrir el CSV.
     */
    public function test_una_cabecera_que_excel_leeria_como_formula_sale_como_texto(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->crearPersonaEn($proyecto, '9100001');

        $respuesta = RespuestaCsv::desdeConsulta(
            'personas.csv',
            ['identificacion', '=HYPERLINK("http://x")'],
            DB::table('personas as p')->where('p.proyecto_id', $proyecto->id)->select(['p.id', 'p.identificacion']),
            'p.id',
            'id',
            static fn (object $p): array => [$p->identificacion, ''],
        );

        ob_start();
        $respuesta->sendContent();
        $csv = (string) ob_get_clean();

        $cabecera = explode("\n", trim(substr($csv, 3)))[0];

        self::assertStringContainsString('\'=HYPERLINK', $cabecera);
    }

    /**
     * Si la descarga revienta a mitad, la huella se escribe igual y dice que
     * quedó incompleta con lo que se alcanzó a emitir. El cliente ya se llevó
     * esas filas: no constar sería peor que constar mal.
     */
    public function test_una_descarga_que_revienta_a_mitad_deja_huella_de_lo_que_alcanzo_a_salir(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        for ($i = 1; $i <= 5; $i++) {
            $this->crearPersonaEn($proyecto, '920000'.$i);
        }

        $emitidas = null;
        $completa = null;

        $respuesta = RespuestaCsv::desdeConsulta(
            'personas.csv',
            ['identificacion'],
            DB::table('personas as p')->where('p.proyecto_id', $proyecto->id)->select(['p.id', 'p.identificacion']),
            'p.id',
            'id',
            static function (object $p): array {
                if ($p->identificacion === '9200004') {
                    throw new RuntimeException('la fila 4 revienta');
                }

                return [$p->identificacion];
            },
            null,
            function (int $n, bool $entera) use (&$emitidas, &$completa): void {
                $emitidas = $n;
                $completa = $entera;
            },
            tamanoLote: 2,
        );

        ob_start();
        try {
            $respuesta->sendContent();
            self::fail('La excepción de la fila tenía que propagarse.');
        } catch (RuntimeException) {
            // Se propaga a propósito: el error es del que llama.
        } finally {
            ob_end_clean();
        }

        self::assertSame(3, $emitidas, 'Tres filas salieron antes de reventar la cuarta.');
        self::assertFalse($completa);
    }
}
