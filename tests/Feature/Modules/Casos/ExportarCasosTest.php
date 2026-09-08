<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Casos;

use App\Modules\Casos\Infrastructure\Http\Livewire\ListadoCasos;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use stdClass;
use Tests\Support\EscenarioMultiMandante;
use Tests\Support\InsertaCti;
use Tests\TestCase;

/**
 * La descarga de casos es la hoja que pide el cliente —cartera completa con
 * saldo, mora, tramo y campos personalizados—, con los filtros del listado,
 * con permiso propio y sin una sola fila de otro proyecto.
 */
final class ExportarCasosTest extends TestCase
{
    use EscenarioMultiMandante;
    use InsertaCti;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_el_csv_solo_trae_casos_del_proyecto_activo(): void
    {
        $mandante = $this->crearMandante();
        $proyectoA = $this->crearProyectoCobranza($mandante);
        $proyectoB = $this->crearProyectoCx($mandante);
        $proyectoAjeno = $this->crearProyectoCobranza($this->crearMandante());

        $this->crearCasoEn($proyectoA, ['persona' => $this->crearPersonaEn($proyectoA, '1000000001')]);
        $this->crearCasoEn($proyectoB, ['persona' => $this->crearPersonaEn($proyectoB, '2000000002')]);
        $this->crearCasoEn($proyectoAjeno, ['persona' => $this->crearPersonaEn($proyectoAjeno, '3000000003')]);

        $csv = $this->actingAs($this->crearSupervisor($proyectoA))
            ->get($this->url($proyectoA))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->streamedContent();

        $this->assertSame(['1000000001'], array_column($this->filasDe($csv), 'identificacion'));
    }

    public function test_un_filtro_con_ids_de_otro_proyecto_devuelve_vacio_y_nunca_amplia(): void
    {
        $mandante = $this->crearMandante();
        $proyectoA = $this->crearProyectoCobranza($mandante);
        $proyectoB = $this->crearProyectoCobranza($mandante);

        $this->crearCasoEn($proyectoA);
        $carteraB = $this->crearCarteraEn($proyectoB);
        $estadoB = $this->crearEstadoCasoEn($proyectoB);
        $this->crearCasoEn($proyectoB, ['cartera' => $carteraB, 'estado' => $estadoB]);

        $supervisor = $this->crearSupervisor($proyectoA);

        $porCartera = $this->actingAs($supervisor)->get($this->url($proyectoA, ['cartera' => (string) $carteraB->id]))->assertOk()->streamedContent();
        $porEstado = $this->actingAs($supervisor)->get($this->url($proyectoA, ['estado' => (string) $estadoB->id]))->assertOk()->streamedContent();

        $this->assertSame([], $this->filasDe($porCartera), 'La cartera de B no existe para A: cero filas, no las de B.');
        $this->assertSame([], $this->filasDe($porEstado), 'El estado de B no existe para A: cero filas, no las de B.');
    }

    public function test_matriz_de_permisos(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();
        $proyecto = $a['proyecto'];

        $this->actingAs($a['supervisor'])->get($this->url($proyecto))->assertOk();
        $this->actingAs($a['adminMandante'])->get($this->url($proyecto))->assertOk();
        $this->actingAs($b['adminMandante'])->get($this->url($proyecto))->assertForbidden();
        $this->actingAs($a['gestor'])->get($this->url($proyecto))->assertForbidden();

        // El auditor audita actividad; la cartera no se la lleva (criterio F32).
        $this->actingAs($this->crearAuditor($proyecto))->get($this->url($proyecto))->assertForbidden();
    }

    public function test_el_csv_devuelve_las_mismas_filas_que_el_listado_con_los_mismos_filtros(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $carteraX = $this->crearCarteraEn($proyecto);
        $carteraY = $this->crearCarteraEn($proyecto);
        $estado = $this->crearEstadoCasoEn($proyecto);

        $this->crearCasoEn($proyecto, ['cartera' => $carteraX, 'estado' => $estado, 'persona' => $this->crearPersonaEn($proyecto, '5000000001')]);
        $this->crearCasoEn($proyecto, ['cartera' => $carteraX, 'estado' => $estado, 'persona' => $this->crearPersonaEn($proyecto, '5000000002')]);
        $this->crearCasoEn($proyecto, ['cartera' => $carteraY, 'estado' => $estado, 'persona' => $this->crearPersonaEn($proyecto, '5000000003')]);
        $this->crearCasoEn($proyecto, ['cartera' => $carteraX, 'estado' => $estado, 'persona' => $this->crearPersonaEn($proyecto, '7000000004')]);

        $supervisor = $this->crearSupervisor($proyecto);
        $this->activarProyecto($proyecto);
        $this->actingAs($supervisor);

        $enPantalla = Livewire::test(ListadoCasos::class)
            ->set('busqueda', '5000')
            ->set('carteraId', (string) $carteraX->id)
            ->viewData('casos');
        $idsPantalla = array_map(static fn (stdClass $c): string => (string) $c->public_id, iterator_to_array($enPantalla));

        $csv = $this->get($this->url($proyecto, ['q' => '5000', 'cartera' => (string) $carteraX->id]))->assertOk()->streamedContent();
        $idsCsv = array_column($this->filasDe($csv), 'caso_public_id');

        sort($idsPantalla);
        sort($idsCsv);

        $this->assertCount(2, $idsPantalla, 'El escenario no está filtrando nada.');
        $this->assertSame($idsPantalla, $idsCsv);
    }

    public function test_un_parametro_con_forma_de_array_no_rompe_la_descarga(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->crearCasoEn($proyecto);

        $this->actingAs($this->crearSupervisor($proyecto))
            ->get($this->url($proyecto).'?q[]=x&cartera[]=1&estado[]=2')
            ->assertOk();
    }

    public function test_la_exportacion_deja_huella_en_auditoria_con_sus_filtros(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto);
        $this->crearCasoEn($proyecto, ['cartera' => $cartera, 'persona' => $this->crearPersonaEn($proyecto, '9000000001')]);

        $this->actingAs($this->crearSupervisor($proyecto))
            ->get($this->url($proyecto, ['q' => '9000', 'cartera' => (string) $cartera->id]))
            ->assertOk()
            ->streamedContent();

        $huella = DB::table('auditorias')->where('evento', 'exportado')->first();

        $this->assertNotNull($huella);
        $this->assertSame('casos', $huella->entidad_tipo);
        $this->assertSame((int) $proyecto->id, (int) $huella->proyecto_id);

        $cambios = json_decode((string) $huella->cambios, true);
        $this->assertSame(['q' => '9000', 'cartera' => (string) $cartera->id], $cambios['filtros']['despues']);
        $this->assertSame(1, $cambios['total_filas']['despues']);
    }

    public function test_en_cobranza_salen_saldo_mora_tramo_y_los_campos_personalizados(): void
    {
        $mandante = $this->crearMandante();
        DB::table('mandantes')->where('id', $mandante->id)->update(['zona_horaria' => 'America/Panama']);
        $proyecto = $this->crearProyectoCobranza($mandante);

        $tramoId = $this->crearTramoMoraEn($proyecto, 'Tramo 31-60');
        $casoId = $this->insertarCasoCobranzaConTramoMora($proyecto, $tramoId);
        DB::table('casos_cobranza')->where('caso_id', $casoId)->update([
            'saldo_total' => 1234.56,
            'dias_mora' => 45,
            'dias_mora_confirmado_en' => '2026-09-02',
        ]);
        // La última gestión es un instante UTC: 01:30 del 8 son las 20:30 del 7 en Panamá.
        DB::table('casos')->where('id', $casoId)->update(['fecha_ultima_gestion' => '2026-09-08 01:30:00']);

        $carteraId = (int) DB::table('casos')->where('id', $casoId)->value('cartera_id');
        $campoTexto = $this->crearCampoDeCaso($proyecto, $carteraId, 'OBSERVACION', 'Observación', 'texto_corto');
        $campoSeleccion = $this->crearCampoDeCaso($proyecto, $carteraId, 'ZONA', 'Zona', 'seleccion_unica');
        $opcionNorte = (int) DB::table('opciones_campo_personalizado')->insertGetId([
            'campo_personalizado_id' => $campoSeleccion,
            'codigo' => 'NORTE',
            'etiqueta' => 'Norte',
            'activo' => true,
            'orden' => 1,
        ]);
        // Dos inserts: en uno multi-fila Laravel toma las columnas de la primera
        // fila, y aquí cada valor va en la columna de su tipo (§7).
        DB::table('valores_campo_personalizado')->insert(
            ['campo_personalizado_id' => $campoTexto, 'entidad_id' => $casoId, 'valor_texto_corto' => 'Llamar por la tarde'],
        );
        DB::table('valores_campo_personalizado')->insert(
            ['campo_personalizado_id' => $campoSeleccion, 'entidad_id' => $casoId, 'valor_opcion_id' => $opcionNorte],
        );

        // Un campo de OTRO proyecto con el mismo código no puede colarse como columna.
        $ajeno = $this->crearProyectoCobranza($mandante);
        $this->crearCampoDeCaso($ajeno, (int) $this->crearCarteraEn($ajeno)->id, 'SECRETO', 'Secreto ajeno', 'texto_corto');

        $csv = $this->actingAs($this->crearSupervisor($proyecto))->get($this->url($proyecto))->assertOk()->streamedContent();
        $filas = $this->filasDe($csv);

        $this->assertCount(1, $filas);
        $fila = $filas[0];
        $this->assertSame('1234.56', $fila['saldo_total']);
        $this->assertSame('45', $fila['dias_mora']);
        $this->assertSame('Tramo 31-60', $fila['tramo_mora']);
        $this->assertSame('2026-09-02', $fila['dias_mora_confirmado_en'], 'Fecha de calendario: tal cual.');
        $this->assertSame('2026-09-07 20:30:00', $fila['fecha_ultima_gestion'], 'Instante: en la hora del cliente.');
        $this->assertSame('Llamar por la tarde', $fila['Observación']);
        $this->assertSame('Norte', $fila['Zona'], 'La selección sale con su etiqueta, no con el id de la opción.');
        $this->assertArrayNotHasKey('Secreto ajeno', $fila);
    }

    public function test_el_recorte_por_cartera_del_rol_gobierna_la_descarga(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $permitida = $this->crearCarteraEn($proyecto);
        $vetada = $this->crearCarteraEn($proyecto);
        $this->crearCasoEn($proyecto, ['cartera' => $permitida]);
        $this->crearCasoEn($proyecto, ['cartera' => $vetada]);

        $supervisor = $this->crearSupervisor($proyecto);
        DB::table('usuario_proyecto_rol_cartera')->insert([
            'usuario_id' => $supervisor->id,
            'proyecto_id' => $proyecto->id,
            'rol_id' => (int) DB::table('roles')->where('codigo', 'SUPERVISOR')->value('id'),
            'cartera_id' => $permitida->id,
        ]);

        $this->actingAs($supervisor)->get($this->url($proyecto, ['cartera' => (string) $permitida->id]))->assertOk();
        $this->actingAs($supervisor)->get($this->url($proyecto, ['cartera' => (string) $vetada->id]))->assertForbidden();

        // Y sin filtro: omitir `?cartera=` no puede ser la forma de llevárselas
        // todas. El permiso se comprueba contra la cartera pedida, así que sin
        // ninguna pedida pasaba, y el CSV salía con la cartera vetada dentro.
        $csv = $this->actingAs($supervisor)->get($this->url($proyecto))->assertOk()->streamedContent();
        $carteras = array_unique(array_column($this->filasDe($csv), 'cartera'));

        $this->assertSame([$permitida->nombre], array_values($carteras));
    }

    public function test_un_proyecto_de_venta_exporta_sus_columnas_propias(): void
    {
        $proyecto = $this->crearProyectoVenta();
        $casoId = $this->insertarCasoLeadVenta($proyecto);
        DB::table('casos_lead_venta')->where('caso_id', $casoId)->update(['valor_estimado' => 1500.00, 'origen_lead' => 'Web']);

        $csv = $this->actingAs($this->crearSupervisor($proyecto))->get($this->url($proyecto))->assertOk()->streamedContent();
        $fila = $this->filasDe($csv)[0];

        $this->assertSame('1500.00', $fila['valor_estimado']);
        $this->assertSame('Web', $fila['origen_lead']);
        $this->assertArrayNotHasKey('tramo_mora', $fila, 'El tramo es de cobranza; en venta no hay columna.');
    }

    private function crearTramoMoraEn(stdClass $proyecto, string $nombre): int
    {
        return (int) DB::table('tramos_mora')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'codigo' => 'TRAMO_'.strtoupper(Str::random(4)),
            'nombre' => $nombre,
            'dias_desde' => 31,
            'dias_hasta' => 60,
            'activo' => true,
            'orden' => 10,
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);
    }

    private function crearCampoDeCaso(stdClass $proyecto, int $carteraId, string $codigo, string $etiqueta, string $tipo): int
    {
        return (int) DB::table('campos_personalizados')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'ambito' => 'caso',
            'ambito_id' => $carteraId,
            'codigo' => $codigo,
            'etiqueta' => $etiqueta,
            'tipo' => $tipo,
            'obligatorio' => false,
            'activo' => true,
            'orden' => 1,
        ]);
    }

    /** @param  array<string, string>  $filtros */
    private function url(stdClass $proyecto, array $filtros = []): string
    {
        return route('proyectos.casos.exportar', ['proyecto_id' => (int) $proyecto->id] + $filtros);
    }

    /**
     * Las filas del CSV como cabecera => valor, saltando el BOM.
     *
     * @return list<array<string, string>>
     */
    private function filasDe(string $csv): array
    {
        $flujo = fopen('php://memory', 'r+');
        self::assertNotFalse($flujo);
        fwrite($flujo, ltrim($csv, "\xEF\xBB\xBF"));
        rewind($flujo);

        $cabeceras = fgetcsv($flujo);
        self::assertIsArray($cabeceras, 'El CSV no tiene ni cabecera.');

        $filas = [];
        while (($valores = fgetcsv($flujo)) !== false) {
            $filas[] = array_combine($cabeceras, $valores);
        }
        fclose($flujo);

        return $filas;
    }
}
