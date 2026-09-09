<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Auditoria;

use App\Modules\Auditoria\Application\Services\ExportadorCsvAuditoria;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use stdClass;
use Tests\Support\EscenarioMultiMandante;
use Tests\TestCase;

/**
 * La exportación de auditoría pagina por clave y deja huella.
 *
 * Antes: `creada_en DESC` + `chunk(500)` (OFFSET) = filesort de la tabla
 * entera en cada lote, 20-25 s con 56.219 filas y un fichero cortado a la
 * mitad sin avisar. Ahora cada lote es `id > último LIMIT 500` sobre el índice
 * `(proyecto_id, id)`, el CSV sale por id ascendente, y la descarga queda en la
 * propia auditoría como evento `exportado`.
 */
final class ExportacionAuditoriaPaginadaTest extends TestCase
{
    use EscenarioMultiMandante;
    use RefreshDatabase;

    /** Más que el lote de 500 de RespuestaCsv, para que haya al menos tres lotes. */
    private const FILAS = 1100;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_la_descarga_deja_una_huella_exportado_con_los_filtros_aplicados(): void
    {
        $mandante = $this->crearMandante();
        $proyecto = $this->crearProyectoCobranza($mandante);
        $auditor = $this->crearAuditor($proyecto);
        $gestor = $this->crearGestor($proyecto);

        $this->insertarEventos($proyecto, 3, $gestor->id);

        $respuesta = $this->actingAs($auditor)->get(route('proyectos.auditoria.exportar', [
            'proyecto_id' => (int) $proyecto->id,
            'entidad_tipo' => 'casos',
            'usuario_id' => (int) $gestor->id,
            'evento' => 'actualizado',
            'desde' => '2020-01-01',
            'hasta' => '2099-12-31',
        ]));

        $respuesta->assertOk();
        $csv = $respuesta->streamedContent();
        self::assertCount(3, $this->filasDe($csv));

        $huella = DB::table('auditorias')->where('evento', 'exportado')->first();

        self::assertNotNull($huella, 'Sacar la auditoría del sistema tiene que quedar en la auditoría.');
        self::assertSame('auditorias', $huella->entidad_tipo);
        self::assertSame((int) $proyecto->id, (int) $huella->proyecto_id);
        self::assertSame((int) $mandante->id, (int) $huella->mandante_id);
        self::assertSame((int) $auditor->id, (int) $huella->usuario_id);

        $cambios = json_decode((string) $huella->cambios, true);

        // MySQL guarda los objetos JSON con las claves reordenadas: se compara
        // el contenido, no el orden en que se escribió.
        $filtros = $cambios['filtros']['despues'];
        ksort($filtros);
        self::assertSame([
            'desde' => '2020-01-01',
            'entidad_tipo' => 'casos',
            'evento' => 'actualizado',
            'hasta' => '2099-12-31',
            'usuario_id' => (int) $gestor->id,
        ], $filtros);
        self::assertSame(3, $cambios['total_filas']['despues']);
    }

    public function test_la_huella_de_la_descarga_del_mandante_se_atribuye_al_cliente_y_no_a_un_proyecto(): void
    {
        ['a' => $a] = $this->montarDosMandantes();

        $this->insertarEventos($a['proyecto'], 2, $a['gestor']->id);

        $this->actingAs($a['adminMandante'])
            ->get(route('admin.auditoria.exportar', ['mandante_id' => (int) $a['mandante']->id]))
            ->assertOk()
            ->streamedContent();

        $huella = DB::table('auditorias')->where('evento', 'exportado')->first();

        self::assertNotNull($huella);
        self::assertNull($huella->proyecto_id);
        self::assertSame((int) $a['mandante']->id, (int) $huella->mandante_id);
        self::assertSame(2, json_decode((string) $huella->cambios, true)['total_filas']['despues']);
    }

    /**
     * Pedir por query string la auditoría de OTRO cliente no la devuelve ni
     * cae de vuelta a la propia: es un 403. Devolver la propia sería peor que
     * un error, porque el fichero se llamaría como el cliente pedido.
     */
    public function test_pedir_la_auditoria_de_un_mandante_ajeno_es_403(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        $this->insertarEventos($b['proyecto'], 3, $b['gestor']->id, 'ajeno');

        $this->actingAs($a['adminMandante'])
            ->get(route('admin.auditoria.exportar', ['mandante_id' => (int) $b['mandante']->id]))
            ->assertForbidden();
    }

    public function test_un_parametro_en_forma_de_array_se_trata_como_sin_filtro(): void
    {
        ['a' => $a] = $this->montarDosMandantes();
        $auditor = $this->crearAuditor($a['proyecto']);

        $this->insertarEventos($a['proyecto'], 2, $a['gestor']->id);

        $url = route('proyectos.auditoria.exportar', ['proyecto_id' => (int) $a['proyecto']->id])
            .'?entidad_tipo[]=x&usuario_id[]=1&desde[]=2020-01-01';

        $respuesta = $this->actingAs($auditor)->get($url);

        $respuesta->assertOk();
        self::assertCount(2, $this->filasDe($respuesta->streamedContent()));

        $this->actingAs($a['adminMandante'])
            ->get(route('admin.auditoria.exportar').'?entidad_tipo[]=x&usuario_id[]=1&mandante_id[]=1')
            ->assertOk();
    }

    public function test_un_proyecto_con_mas_filas_que_el_lote_sale_completo_y_ordenado_por_id(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();
        $auditor = $this->crearAuditor($a['proyecto']);

        $this->insertarEventos($a['proyecto'], self::FILAS, $a['gestor']->id);
        $this->insertarEventos($b['proyecto'], 7, $b['gestor']->id, 'ajeno');

        $respuesta = $this->actingAs($auditor)->get(route('proyectos.auditoria.exportar', [
            'proyecto_id' => (int) $a['proyecto']->id,
        ]));

        $respuesta->assertOk();
        $filas = $this->filasDe($respuesta->streamedContent());

        self::assertCount(self::FILAS, $filas, 'Un lote perdido entre página y página deja un CSV corto que parece completo.');

        // `entidad_id` se insertó 1..N en orden, así que sigue al id; y
        // `creada_en` se insertó al revés, para demostrar que ya no manda.
        $entidadIds = array_map(static fn (array $f): int => (int) $f[4], $filas);
        self::assertSame(range(1, self::FILAS), $entidadIds);

        self::assertStringNotContainsString('ajeno', $respuesta->streamedContent());
        $this->assertNoSeFiltra($respuesta->streamedContent(), $b, 'CSV paginado de /proyectos/{id}/auditoria/exportar');
    }

    public function test_la_descarga_del_mandante_tambien_sale_completa_cuando_supera_el_lote(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        $this->insertarEventos($a['proyecto'], self::FILAS, $a['gestor']->id);
        $this->insertarEventos($b['proyecto'], 7, $b['gestor']->id, 'ajeno');

        $respuesta = $this->actingAs($a['adminMandante'])->get(route('admin.auditoria.exportar', [
            'mandante_id' => (int) $a['mandante']->id,
        ]));

        $respuesta->assertOk();
        $csv = $respuesta->streamedContent();

        self::assertCount(self::FILAS, $this->filasDe($csv));
        self::assertStringNotContainsString('ajeno', $csv);
        $this->assertNoSeFiltra($csv, $b, 'CSV paginado de /admin/auditoria/exportar');
    }

    /**
     * EXPLAIN sobre la consulta REAL de un lote (la del exportador, con el
     * `id > último` y el LIMIT que añade chunkById). Se mira la consulta del
     * exportador y no una escrita a mano porque el índice sólo se usa forzado:
     * dejado a su cálculo, el optimizador de MySQL 8.4 prefiere ref por otro
     * índice más filesort, y una consulta paralela habría dado verde con la de
     * verdad ordenando la tabla entera en cada lote.
     */
    public function test_la_paginacion_por_clave_recorre_el_indice_proyecto_id_sin_ordenar(): void
    {
        self::assertTrue(
            Schema::hasIndex('auditorias', ExportadorCsvAuditoria::INDICE_PAGINACION),
            'Sin el índice (proyecto_id, id) cada lote vuelve a ser un filesort de la tabla entera.',
        );

        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();
        $this->insertarEventos($a['proyecto'], self::FILAS, $a['gestor']->id);
        $this->insertarEventos($b['proyecto'], 300, $b['gestor']->id);

        $ultimoId = (int) DB::table('auditorias')->where('proyecto_id', $a['proyecto']->id)->orderBy('id')->skip(499)->value('id');

        $lote = app(ExportadorCsvAuditoria::class)
            ->consultaDelProyecto((int) $a['proyecto']->id)
            ->where('a.id', '>', $ultimoId)
            ->orderBy('a.id')
            ->limit(500);

        /** @var list<stdClass> $plan */
        $plan = DB::select('EXPLAIN '.$lote->toSql(), $lote->getBindings());

        self::assertNotEmpty($plan);
        $paso = $plan[0];

        self::assertSame('a', $paso->table, 'El primer paso del plan tiene que ser la propia tabla auditorias. Plan: '.json_encode($plan));
        self::assertContains(
            $paso->key,
            [ExportadorCsvAuditoria::INDICE_PAGINACION, 'PRIMARY'],
            'El lote paginado tiene que ir por (proyecto_id, id) o por la PK; con otra clave vuelve el filesort. Plan: '.json_encode($paso),
        );
        self::assertStringNotContainsString('filesort', (string) ($paso->Extra ?? ''), 'Plan: '.json_encode($paso));
    }

    /**
     * N eventos del proyecto, con `entidad_id` 1..N en orden de inserción y
     * `creada_en` decreciente: el orden por fecha y el orden por id van al
     * revés a propósito.
     */
    private function insertarEventos(stdClass $proyecto, int $cuantos, int $usuarioId, string $entidadTipo = 'casos'): void
    {
        $base = Carbon::now()->subDay();
        $filas = [];

        for ($i = 1; $i <= $cuantos; $i++) {
            $filas[] = [
                'public_id' => (string) Str::ulid(),
                'proyecto_id' => (int) $proyecto->id,
                'mandante_id' => (int) $proyecto->mandante_id,
                'usuario_id' => $usuarioId,
                'entidad_tipo' => $entidadTipo,
                'entidad_id' => $i,
                'evento' => 'actualizado',
                'datos_antes' => json_encode(['proyecto_codigo' => (string) $proyecto->codigo]),
                'datos_despues' => json_encode(['proyecto_codigo' => (string) $proyecto->codigo]),
                'creada_en' => $base->copy()->subSeconds($i),
            ];
        }

        foreach (array_chunk($filas, 500) as $lote) {
            DB::table('auditorias')->insert($lote);
        }
    }

    /**
     * Las filas de datos del CSV (sin BOM ni cabecera), ya partidas en celdas.
     *
     * @return list<list<string>>
     */
    private function filasDe(string $csv): array
    {
        $lineas = array_values(array_filter(explode("\n", trim(substr($csv, 3)))));
        array_shift($lineas);

        return array_map(static fn (string $l): array => array_map('strval', str_getcsv($l)), $lineas);
    }
}
