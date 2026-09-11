<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Importaciones;

use App\Modules\CamposPersonalizados\Domain\ValueObjects\TipoCampo;
use App\Modules\Importaciones\Application\UseCases\EjecutarImportacionDinamica;
use App\Modules\Importaciones\Application\UseCases\EjecutarImportacionInput;
use App\Modules\Importaciones\Application\UseCases\ProcesarFilaDinamica;
use App\Modules\Importaciones\Application\UseCases\ProcesarFilaInput;
use App\Modules\Importaciones\Domain\Enums\AccionColumna;
use App\Modules\Importaciones\Domain\Enums\EstadoFila;
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
 * Qué promete cada modo de importación, comprobado contra el motor que corre.
 *
 * La versión anterior de este fichero probaba los mismos tres modos contra
 * `ProcesarImportacionPersonas`, el importador de columnas fijas que se retiró:
 * un test verde sobre una clase que ninguna pantalla podía invocar. Las
 * promesas son las mismas y el sitio donde se cumplen es `ProcesarFilaDinamica`,
 * así que el fichero se queda con su nombre y cambia de sujeto.
 *
 * Sobre casos y no sobre personas porque el asistente ya no importa personas
 * sueltas: una persona nace como efecto de crear su caso.
 */
final class ModosImportacionTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_merge_keeps_filled_ticket_text_and_completes_an_empty_description(): void
    {
        $project = $this->crearProyectoCx();
        $portfolio = $this->crearCarteraEn($project);
        $person = $this->crearPersonaEn($project);
        $case = $this->crearCasoEn($project, ['cartera' => $portfolio, 'persona' => $person]);
        DB::table('casos_ticket_cx')->insert([
            'proyecto_id' => $project->id, 'caso_id' => $case, 'codigo_ticket' => 'CX-MERGE',
            'asunto' => 'Original subject', 'descripcion' => null, 'fecha_reporte' => now(),
        ]);
        $this->mergeNative($project, $portfolio, $person, TargetImportacion::CASO_TICKET_CX, 'CX-MERGE', [
            'asunto' => 'Incoming subject', 'descripcion' => 'Completed description',
        ]);
        $this->assertDatabaseHas('casos_ticket_cx', [
            'caso_id' => $case, 'asunto' => 'Original subject', 'descripcion' => 'Completed description',
        ]);
    }

    public function test_merge_reads_the_actual_sales_value_column_and_keeps_the_existing_zero_rule(): void
    {
        $project = $this->crearProyectoVenta();
        $portfolio = $this->crearCarteraEn($project);
        $person = $this->crearPersonaEn($project);
        $case = $this->crearCasoEn($project, ['cartera' => $portfolio, 'persona' => $person]);
        DB::table('casos_lead_venta')->insert([
            'proyecto_id' => $project->id, 'caso_id' => $case, 'codigo_lead' => 'SALE-MERGE',
            'valor_estimado' => 1000, 'origen_lead' => null, 'fecha_primer_contacto' => now()->toDateString(),
        ]);
        $values = ['valor_estimado_monto' => '250.00', 'origen_lead' => 'Referral'];
        $this->mergeNative($project, $portfolio, $person, TargetImportacion::CASO_LEAD_VENTA, 'SALE-MERGE', $values);
        $this->assertDatabaseHas('casos_lead_venta', ['caso_id' => $case, 'valor_estimado' => 1000, 'origen_lead' => 'Referral']);

        DB::table('casos_lead_venta')->where('caso_id', $case)->update(['valor_estimado' => 0]);
        $this->mergeNative($project, $portfolio, $person, TargetImportacion::CASO_LEAD_VENTA, 'SALE-MERGE', $values);
        $this->assertDatabaseHas('casos_lead_venta', ['caso_id' => $case, 'valor_estimado' => 250]);
    }

    /** @param array<string, string> $values */
    private function mergeNative(stdClass $project, stdClass $portfolio, stdClass $person, TargetImportacion $target, string $reference, array $values): void
    {
        $columns = [new ColumnaExcel('IDENTITY', TipoCampo::TEXTO_CORTO, 'identificacion', true, false, AccionColumna::MAPEAR_SISTEMA)];
        foreach ($values as $code => $value) {
            $columns[] = new ColumnaExcel(strtoupper($code), $code === 'valor_estimado_monto' ? TipoCampo::NUMERO_DECIMAL : TipoCampo::TEXTO_CORTO,
                $code, false, false, AccionColumna::MAPEAR_SISTEMA);
        }
        $schema = new EsquemaImportacion($target, $project->id, $portfolio->id, ModoImportacion::MERGE, $columns);
        $result = app(ProcesarFilaDinamica::class)->execute(new ProcesarFilaInput(
            ['identificacion' => $person->identificacion, 'id_cpelegido' => $reference, ...$values],
            $schema, 1, [], ['TEST' => $person->tipo_identificacion_id],
        ));
        $this->assertSame(EstadoFila::PROCESADA, $result->resultadoFila->estado);
    }

    public function test_completar_vacios_rellena_lo_nulo_y_respeta_lo_que_ya_estaba(): void
    {
        [$proyecto, $cartera] = $this->escenario();
        $this->casoCon($proyecto, $cartera, ['saldo_capital' => 1000.00, 'saldo_interes' => null]);

        $this->importar($proyecto, $cartera, ModoImportacion::MERGE, [
            'saldo_capital' => '250',
            'saldo_interes' => '75',
        ]);

        $cti = $this->cti($proyecto);
        $this->assertSame('1000.000', (string) $cti->saldo_capital, 'Lo que ya tenía valor no se toca.');
        $this->assertSame('75.000', (string) $cti->saldo_interes, 'Lo que estaba nulo se rellena.');
    }

    public function test_insertar_y_actualizar_pisa_lo_que_el_archivo_trae(): void
    {
        [$proyecto, $cartera] = $this->escenario();
        $this->casoCon($proyecto, $cartera, ['saldo_capital' => 1000.00, 'saldo_interes' => 40.00]);

        $this->importar($proyecto, $cartera, ModoImportacion::UPSERT, [
            'saldo_capital' => '250',
            'saldo_interes' => '75',
        ]);

        $cti = $this->cti($proyecto);
        $this->assertSame('250.000', (string) $cti->saldo_capital);
        $this->assertSame('75.000', (string) $cti->saldo_interes);
    }

    /**
     * La celda vacía de una hoja de cálculo no es una orden de borrar.
     *
     * El asistente ni siquiera la mete en el payload, así que el motor no
     * puede distinguirla de una columna ausente, y en los dos casos deja el
     * valor como estaba. Es la garantía que hacía falta para que un archivo
     * parcial —el que sólo trae los saldos que cambiaron— no vacíe el resto.
     */
    public function test_una_celda_vacia_del_archivo_no_borra_el_valor_guardado(): void
    {
        [$proyecto, $cartera] = $this->escenario();
        $this->casoCon($proyecto, $cartera, ['saldo_capital' => 1000.00, 'saldo_interes' => 40.00]);

        $this->importar($proyecto, $cartera, ModoImportacion::UPSERT, ['saldo_capital' => '250']);

        $cti = $this->cti($proyecto);
        $this->assertSame('250.000', (string) $cti->saldo_capital);
        $this->assertSame('40.000', (string) $cti->saldo_interes, 'La columna que el archivo no trae se queda como estaba.');
    }

    public function test_saltar_duplicados_no_toca_el_caso_y_marca_la_fila(): void
    {
        [$proyecto, $cartera] = $this->escenario();
        $this->casoCon($proyecto, $cartera, ['saldo_capital' => 1000.00, 'saldo_interes' => 40.00]);

        $importacionId = $this->importar($proyecto, $cartera, ModoImportacion::SKIP_DUPLICADOS, [
            'saldo_capital' => '250',
            'saldo_interes' => '75',
        ]);

        $cti = $this->cti($proyecto);
        $this->assertSame('1000.000', (string) $cti->saldo_capital, 'Saltar es no escribir: ni una columna.');
        $this->assertSame('40.000', (string) $cti->saldo_interes);

        $fila = DB::table('importacion_filas')->where('importacion_id', $importacionId)->first();
        $this->assertSame('duplicada', (string) $fila->estado);
        $this->assertSame(1, (int) DB::table('importaciones')->where('id', $importacionId)->value('duplicadas'));
    }

    /**
     * Saltar duplicados NO es «insertar lo que falta»: una fila cuyo caso no
     * existe queda inválida y lo dice. El nombre del modo se presta a leerlo
     * como un upsert tolerante, y no lo es.
     */
    public function test_saltar_duplicados_tampoco_crea_lo_que_no_existe(): void
    {
        [$proyecto, $cartera] = $this->escenario();

        $importacionId = $this->importar($proyecto, $cartera, ModoImportacion::SKIP_DUPLICADOS, ['saldo_capital' => '250']);

        $fila = DB::table('importacion_filas')->where('importacion_id', $importacionId)->first();
        $this->assertSame('invalida', (string) $fila->estado);
        $this->assertStringContainsString('no se crean registros nuevos', (string) $fila->mensaje_error);
        $this->assertSame(0, DB::table('casos')->where('proyecto_id', $proyecto->id)->count());
    }

    /** @return array{0: stdClass, 1: stdClass} */
    private function escenario(): array
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto);
        $this->crearEstadoCasoEn($proyecto, 'ABIERTO');

        return [$proyecto, $cartera];
    }

    /**
     * Un caso de cobranza ya cargado, con los saldos que se le digan.
     *
     * @param  array<string, float|null>  $saldos
     */
    private function casoCon(stdClass $proyecto, stdClass $cartera, array $saldos): void
    {
        $persona = $this->crearPersonaEn($proyecto, '8-990-429');
        $casoId = $this->crearCasoEn($proyecto, ['cartera' => $cartera, 'persona' => $persona]);

        DB::table('casos_cobranza')->insert(array_merge([
            'caso_id' => $casoId,
            'proyecto_id' => $proyecto->id,
            'numero_prestamo' => 'PR-MODOS',
            'monto_original' => 1000.00,
            'saldo_total' => 1000.00,
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ], $saldos));
    }

    /**
     * Corre el motor con un esquema de cobranza y una sola fila.
     *
     * @param  array<string, string>  $valores
     */
    private function importar(stdClass $proyecto, stdClass $cartera, ModoImportacion $modo, array $valores): int
    {
        $esquema = new EsquemaImportacion(
            target: TargetImportacion::CASO_COBRANZA,
            proyectoId: (int) $proyecto->id,
            carteraId: (int) $cartera->id,
            modo: $modo,
            columnas: [
                new ColumnaExcel('CEDULA', TipoCampo::TEXTO_CORTO, 'identificacion', true, false, AccionColumna::MAPEAR_SISTEMA),
                new ColumnaExcel('CUENTA', TipoCampo::TEXTO_CORTO, null, false, true, AccionColumna::IGNORAR),
                new ColumnaExcel('CAPITAL', TipoCampo::NUMERO_DECIMAL, 'saldo_capital', false, false, AccionColumna::MAPEAR_SISTEMA),
                new ColumnaExcel('INTERES', TipoCampo::NUMERO_DECIMAL, 'saldo_interes', false, false, AccionColumna::MAPEAR_SISTEMA),
            ],
        );

        $importacionId = (int) DB::table('importaciones')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'tipo_entidad' => 'caso_cobranza',
            'modo' => $modo->value,
            'estado' => EstadoImportacion::PREPARADA->value,
            'usuario_id' => $this->crearSupervisor($proyecto)->id,
            'nombre_archivo' => 'modos.csv',
            'total_filas' => 1,
            'esquema' => $esquema->serializar(),
        ]);

        DB::table('importacion_filas')->insert([
            'importacion_id' => $importacionId,
            'proyecto_id' => $proyecto->id,
            'numero_fila' => 1,
            'estado' => 'pendiente',
            'payload' => json_encode(array_merge([
                'identificacion' => '8-990-429',
                'id_cpelegido' => 'PR-MODOS',
            ], $valores), JSON_THROW_ON_ERROR),
        ]);

        app(EjecutarImportacionDinamica::class)->execute(new EjecutarImportacionInput(
            importacionId: $importacionId,
            chunkSize: 100,
        ));

        return $importacionId;
    }

    private function cti(stdClass $proyecto): stdClass
    {
        /** @var stdClass $fila */
        $fila = DB::table('casos_cobranza')
            ->where('proyecto_id', $proyecto->id)
            ->where('numero_prestamo', 'PR-MODOS')
            ->first();

        return $fila;
    }
}
