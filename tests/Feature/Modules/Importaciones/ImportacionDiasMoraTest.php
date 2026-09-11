<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Importaciones;

use App\Modules\CamposPersonalizados\Domain\ValueObjects\TipoCampo;
use App\Modules\Importaciones\Application\UseCases\EjecutarImportacionDinamica;
use App\Modules\Importaciones\Application\UseCases\EjecutarImportacionInput;
use App\Modules\Importaciones\Domain\Enums\AccionColumna;
use App\Modules\Importaciones\Domain\Enums\EstadoImportacion;
use App\Modules\Importaciones\Domain\Enums\ModoImportacion;
use App\Modules\Importaciones\Domain\Enums\TargetImportacion;
use App\Modules\Importaciones\Domain\ValueObjects\ColumnaExcel;
use App\Modules\Importaciones\Domain\ValueObjects\EsquemaImportacion;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * La importación es una de las fuentes que afirman la mora: cuando escribe
 * `dias_mora` tiene que decir a qué día corresponde, en el calendario del
 * mandante, porque el envejecimiento nocturno cuenta a partir de ahí.
 */
final class ImportacionDiasMoraTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_completar_vacios_conserva_el_cero_existente_porque_al_dia_es_un_valor(): void
    {
        [$proyecto, $cartera] = $this->escenario();
        $this->casoCobranzaEn($proyecto, $cartera, diasMora: 0);

        $this->importar($proyecto, $cartera, ModoImportacion::MERGE, ['dias_mora' => '45']);

        $cti = $this->cti($proyecto);
        $this->assertSame(0, (int) $cti->dias_mora);
        $this->assertNull($cti->dias_mora_actualizado_en);
        $this->assertNull($cti->dias_mora_confirmado_en);
    }

    public function test_completar_vacios_si_rellena_una_mora_nula(): void
    {
        [$proyecto, $cartera] = $this->escenario();
        $this->casoCobranzaEn($proyecto, $cartera, diasMora: null);

        $this->importar($proyecto, $cartera, ModoImportacion::MERGE, ['dias_mora' => '45']);

        $this->assertSame(45, (int) $this->cti($proyecto)->dias_mora);
    }

    public function test_un_archivo_con_dias_mora_ancla_las_dos_fechas_al_hoy_del_mandante(): void
    {
        [$proyecto, $cartera] = $this->escenario(zona: 'America/Panama');
        $this->casoCobranzaEn($proyecto, $cartera, diasMora: 10);

        // 03:00 UTC del día 8 son las 22:00 del día 7 en Panamá: si la fecha
        // saliera del reloj del servidor, la mora quedaría anclada un día tarde.
        Carbon::setTestNow(Carbon::parse('2026-09-08 03:00:00', 'UTC'));

        $this->importar($proyecto, $cartera, ModoImportacion::UPSERT, ['dias_mora' => '45']);

        $cti = $this->cti($proyecto);
        $this->assertSame(45, (int) $cti->dias_mora);
        $this->assertSame('2026-09-07', (string) $cti->dias_mora_actualizado_en);
        $this->assertSame('2026-09-07', (string) $cti->dias_mora_confirmado_en);
    }

    public function test_un_archivo_sin_la_columna_dias_mora_no_toca_las_fechas(): void
    {
        [$proyecto, $cartera] = $this->escenario();
        $this->casoCobranzaEn($proyecto, $cartera, diasMora: 10, ancla: '2026-01-15');

        $this->importar($proyecto, $cartera, ModoImportacion::UPSERT, ['saldo_capital' => '250'], conDiasMora: false);

        $cti = $this->cti($proyecto);
        $this->assertSame('250.000', (string) $cti->saldo_capital);
        $this->assertSame(10, (int) $cti->dias_mora);
        $this->assertSame('2026-01-15', (string) $cti->dias_mora_actualizado_en);
        $this->assertSame('2026-01-15', (string) $cti->dias_mora_confirmado_en);
    }

    public function test_una_mora_fuera_de_rango_deja_la_fila_invalida_con_el_mensaje_del_value_object(): void
    {
        [$proyecto, $cartera] = $this->escenario();
        $this->casoCobranzaEn($proyecto, $cartera, diasMora: 10, ancla: '2026-01-15');

        $importacionId = $this->importar($proyecto, $cartera, ModoImportacion::UPSERT, ['dias_mora' => '99999', 'saldo_capital' => '250']);

        $fila = DB::table('importacion_filas')->where('importacion_id', $importacionId)->first();
        $this->assertSame('invalida', (string) $fila->estado);
        $this->assertStringContainsString('superan los 40 años', (string) $fila->mensaje_error);

        $cti = $this->cti($proyecto);
        $this->assertSame(10, (int) $cti->dias_mora, 'Se valida antes de escribir: nada de la fila entra.');
        $this->assertSame('1000.000', (string) $cti->saldo_capital);
        $this->assertSame('2026-01-15', (string) $cti->dias_mora_actualizado_en);
    }

    /**
     * Un «días en atraso» negativo son días POR VENCER, no mora, y la columna
     * del CTI es `int unsigned`. El alta ya lo dejaba pasar sin escribir nada;
     * la actualización lo rechazaba con un error de tipo. El mismo archivo
     * entraba entero por un camino y se caía por el otro, así que ahora los
     * dos hacen lo mismo: la fila entra, y la mora se queda como estaba.
     */
    public function test_una_mora_negativa_no_invalida_la_fila_y_deja_la_mora_como_estaba(): void
    {
        [$proyecto, $cartera] = $this->escenario();
        $this->casoCobranzaEn($proyecto, $cartera, diasMora: 10, ancla: '2026-01-15');

        $importacionId = $this->importar($proyecto, $cartera, ModoImportacion::UPSERT, ['dias_mora' => '-15', 'saldo_capital' => '250']);

        $fila = DB::table('importacion_filas')->where('importacion_id', $importacionId)->first();
        $this->assertSame('procesada', (string) $fila->estado);

        $cti = $this->cti($proyecto);
        $this->assertSame(10, (int) $cti->dias_mora, 'La mora no se toca.');
        $this->assertSame('2026-01-15', (string) $cti->dias_mora_actualizado_en, 'Ni su ancla: nadie ha afirmado nada nuevo.');
        $this->assertSame('250.000', (string) $cti->saldo_capital, 'El resto de la fila sí entra.');
    }

    public function test_las_personas_existentes_del_lote_se_resuelven_con_una_consulta_por_tipo(): void
    {
        [$proyecto, $cartera] = $this->escenario();
        $filas = [];
        foreach (['8-100-1', '8-100-2', '8-100-3'] as $i => $identificacion) {
            $this->crearPersonaEn($proyecto, $identificacion);
            $filas[] = ['identificacion' => $identificacion, 'cuenta' => 'PR-'.$i, 'id_cpelegido' => 'PR-'.$i, 'dias_mora' => '5'];
        }
        $filas[] = ['identificacion' => '8-100-9', 'cuenta' => 'PR-9', 'id_cpelegido' => 'PR-9', 'dias_mora' => '5'];

        DB::enableQueryLog();
        $this->importar($proyecto, $cartera, ModoImportacion::UPSERT, filas: $filas);
        $consultas = DB::getQueryLog();
        DB::disableQueryLog();

        $porLote = array_filter($consultas, static fn (array $c): bool => str_contains($c['query'], 'from `personas`') && str_contains($c['query'], '`identificacion` in ('));
        $unaAUna = array_filter($consultas, static fn (array $c): bool => str_contains($c['query'], 'from `personas`')
            && str_contains($c['query'], '`identificacion` = ?')
            && array_intersect(['8-100-1', '8-100-2', '8-100-3'], $c['bindings']) !== []);

        $this->assertCount(1, $porLote, 'Un solo whereIn por tipo de identificación para todo el lote.');
        $this->assertCount(0, $unaAUna, 'Ninguna de las existentes se busca aparte; sólo la nueva, que no estaba en el mapa.');
    }

    /** @return array{0: stdClass, 1: stdClass} */
    private function escenario(string $zona = 'UTC'): array
    {
        $mandante = $this->crearMandante();
        DB::table('mandantes')->where('id', $mandante->id)->update(['zona_horaria' => $zona]);

        $proyecto = $this->crearProyectoCobranza($mandante);
        $cartera = $this->crearCarteraEn($proyecto);
        $this->crearEstadoCasoEn($proyecto, 'ABIERTO');

        return [$proyecto, $cartera];
    }

    private function casoCobranzaEn(stdClass $proyecto, stdClass $cartera, ?int $diasMora, ?string $ancla = null): void
    {
        $persona = $this->crearPersonaEn($proyecto, '8-990-429');
        $casoId = $this->crearCasoEn($proyecto, ['cartera' => $cartera, 'persona' => $persona]);

        DB::table('casos_cobranza')->insert([
            'caso_id' => $casoId,
            'proyecto_id' => $proyecto->id,
            'numero_prestamo' => 'PR-MORA',
            'monto_original' => 1000.00,
            'saldo_capital' => 1000.00,
            'saldo_total' => 1000.00,
            'cuota_mensual' => 100.00,
            'cuotas_totales' => 12,
            'dias_mora' => $diasMora,
            'dias_mora_actualizado_en' => $ancla,
            'dias_mora_confirmado_en' => $ancla,
            'fecha_desembolso' => Carbon::today()->subYear(),
            'fecha_vencimiento' => Carbon::today()->addYear(),
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);
    }

    /**
     * Corre el motor sobre una importación preparada a mano con un esquema
     * de cobranza (identificación, cuenta y, si se pide, días de mora).
     *
     * @param  array<string, string>  $valores  Columnas de sistema para la única fila (si no se pasan `$filas`).
     * @param  list<array<string, string>>|null  $filas
     */
    private function importar(stdClass $proyecto, stdClass $cartera, ModoImportacion $modo, array $valores = [], bool $conDiasMora = true, ?array $filas = null): int
    {
        $columnas = [
            new ColumnaExcel('CEDULA', TipoCampo::TEXTO_CORTO, 'identificacion', true, false, AccionColumna::MAPEAR_SISTEMA),
            new ColumnaExcel('CUENTA', TipoCampo::TEXTO_CORTO, null, false, true, AccionColumna::IGNORAR),
            new ColumnaExcel('CAPITAL', TipoCampo::NUMERO_DECIMAL, 'saldo_capital', false, false, AccionColumna::MAPEAR_SISTEMA),
        ];
        if ($conDiasMora) {
            $columnas[] = new ColumnaExcel('DIAS EN ATRASO', TipoCampo::NUMERO_ENTERO, 'dias_mora', false, false, AccionColumna::MAPEAR_SISTEMA);
        }

        $esquema = new EsquemaImportacion(
            target: TargetImportacion::CASO_COBRANZA,
            proyectoId: (int) $proyecto->id,
            carteraId: (int) $cartera->id,
            modo: $modo,
            columnas: $columnas,
        );

        $filas ??= [array_merge(['identificacion' => '8-990-429', 'id_cpelegido' => 'PR-MORA'], $valores)];

        $importacionId = (int) DB::table('importaciones')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'tipo_entidad' => 'caso_cobranza',
            'modo' => $modo->value,
            'estado' => EstadoImportacion::PREPARADA->value,
            'usuario_id' => $this->crearSupervisor($proyecto)->id,
            'nombre_archivo' => 'mora.csv',
            'total_filas' => count($filas),
            'esquema' => $esquema->serializar(),
        ]);

        foreach (array_values($filas) as $i => $fila) {
            DB::table('importacion_filas')->insert([
                'importacion_id' => $importacionId,
                'proyecto_id' => $proyecto->id,
                'numero_fila' => $i + 1,
                'estado' => 'pendiente',
                'payload' => json_encode($fila, JSON_THROW_ON_ERROR),
            ]);
        }

        app(EjecutarImportacionDinamica::class)->execute(new EjecutarImportacionInput(
            importacionId: $importacionId,
            chunkSize: 100,
        ));

        return $importacionId;
    }

    private function cti(stdClass $proyecto): stdClass
    {
        return DB::table('casos_cobranza')
            ->where('proyecto_id', $proyecto->id)
            ->where('numero_prestamo', 'PR-MORA')
            ->first();
    }
}
