<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Personas;

use App\Modules\Personas\Infrastructure\Http\Livewire\ListadoPersonas;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use stdClass;
use Tests\Support\EscenarioMultiMandante;
use Tests\TestCase;

/**
 * La descarga de personas es el listado que se está mirando, con sus filtros,
 * con permiso propio y dejando huella. Nunca una fila de otro proyecto.
 */
final class ExportarPersonasTest extends TestCase
{
    use EscenarioMultiMandante;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_el_csv_solo_trae_personas_del_proyecto_activo(): void
    {
        $mandante = $this->crearMandante();
        $proyectoA = $this->crearProyectoCobranza($mandante);
        $proyectoB = $this->crearProyectoCx($mandante);
        $proyectoAjeno = $this->crearProyectoCobranza($this->crearMandante());

        $this->crearPersonaOperativaEn($proyectoA, '1000000001');
        $this->crearPersonaOperativaEn($proyectoB, '2000000002');
        $this->crearPersonaOperativaEn($proyectoAjeno, '3000000003');

        $csv = $this->actingAs($this->crearSupervisor($proyectoA))
            ->get($this->url($proyectoA))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->streamedContent();

        $identificaciones = array_column($this->filasDe($csv), 'identificacion');

        $this->assertSame(['1000000001'], $identificaciones, 'Ni el otro proyecto del mismo mandante ni el de otro mandante.');
    }

    public function test_el_csv_devuelve_las_mismas_filas_que_el_listado_con_los_mismos_filtros(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->crearPersonaOperativaEn($proyecto, '5000000001');
        $this->crearPersonaOperativaEn($proyecto, '5000000002');
        $juridica = $this->crearPersonaOperativaEn($proyecto, '5000000003');
        DB::table('personas')->where('id', $juridica->id)->update(['tipo_persona' => 'juridica', 'razon_social' => 'Empresa 5000']);
        $this->crearPersonaOperativaEn($proyecto, '7000000004');

        $supervisor = $this->crearSupervisor($proyecto);
        $this->activarProyecto($proyecto);
        $this->actingAs($supervisor);

        $enPantalla = Livewire::test(ListadoPersonas::class)
            ->set('busqueda', '5000')
            ->set('tipoPersona', 'fisica')
            ->viewData('personas');
        $idsPantalla = array_map(static fn (stdClass $p): string => (string) $p->identificacion, iterator_to_array($enPantalla));

        $csv = $this->get($this->url($proyecto, ['q' => '5000', 'tipo' => 'fisica']))->assertOk()->streamedContent();
        $idsCsv = array_column($this->filasDe($csv), 'identificacion');

        sort($idsPantalla);
        sort($idsCsv);

        $this->assertSame(['5000000001', '5000000002'], $idsPantalla, 'El escenario no está filtrando nada.');
        $this->assertSame($idsPantalla, $idsCsv, 'Lo que se ve y lo que se descarga tienen que ser lo mismo.');
    }

    public function test_matriz_de_permisos(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();
        $proyecto = $a['proyecto'];

        $this->actingAs($a['supervisor'])->get($this->url($proyecto))->assertOk();
        $this->actingAs($a['adminMandante'])->get($this->url($proyecto))->assertOk();
        $this->actingAs($b['adminMandante'])->get($this->url($proyecto))->assertForbidden();
        $this->actingAs($a['gestor'])->get($this->url($proyecto))->assertForbidden();

        // El auditor audita actividad; el padrón no se lo lleva (criterio F32).
        $this->actingAs($this->crearAuditor($proyecto))->get($this->url($proyecto))->assertForbidden();
    }

    public function test_un_parametro_con_forma_de_array_no_rompe_la_descarga(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->crearPersonaOperativaEn($proyecto);

        $this->actingAs($this->crearSupervisor($proyecto))
            ->get($this->url($proyecto).'?q[]=x&tipo[]=1')
            ->assertOk();
    }

    public function test_el_filtro_de_tipo_estrecha_y_nunca_amplia(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->crearPersonaOperativaEn($proyecto, '8000000001');
        $juridica = $this->crearPersonaOperativaEn($proyecto, '8000000002');
        DB::table('personas')->where('id', $juridica->id)->update(['tipo_persona' => 'juridica']);

        $csv = $this->actingAs($this->crearSupervisor($proyecto))
            ->get($this->url($proyecto, ['tipo' => 'juridica']))
            ->assertOk()
            ->streamedContent();

        $this->assertSame(['8000000002'], array_column($this->filasDe($csv), 'identificacion'));
    }

    public function test_la_exportacion_deja_huella_en_auditoria(): void
    {
        $mandante = $this->crearMandante();
        $proyecto = $this->crearProyectoCobranza($mandante);
        $this->crearPersonaOperativaEn($proyecto, '9000000001');
        $supervisor = $this->crearSupervisor($proyecto);

        $this->actingAs($supervisor)->get($this->url($proyecto, ['q' => '9000']))->assertOk()->streamedContent();

        $huella = DB::table('auditorias')->where('evento', 'exportado')->first();

        $this->assertNotNull($huella, 'Sacar el padrón del sistema tiene que quedar en la auditoría.');
        $this->assertSame('personas', $huella->entidad_tipo);
        $this->assertSame((int) $proyecto->id, (int) $huella->proyecto_id);
        $this->assertSame((int) $mandante->id, (int) $huella->mandante_id);
        $this->assertSame((int) $supervisor->id, (int) $huella->usuario_id);

        $cambios = json_decode((string) $huella->cambios, true);
        $this->assertSame(['q' => '9000'], $cambios['filtros']['despues']);
        $this->assertSame(1, $cambios['total_filas']['despues']);
    }

    public function test_los_instantes_salen_en_la_hora_del_cliente_y_las_fechas_tal_cual(): void
    {
        $mandante = $this->crearMandante();
        DB::table('mandantes')->where('id', $mandante->id)->update(['zona_horaria' => 'America/Panama']);
        $proyecto = $this->crearProyectoCobranza($mandante);

        $persona = $this->crearPersonaOperativaEn($proyecto, '6000000001');
        // 01:30 UTC del 8 son las 20:30 del 7 en Panamá; la fecha de nacimiento
        // es de calendario y no se mueve.
        DB::table('personas')->where('id', $persona->id)->update([
            'creada_en' => '2026-09-08 01:30:00',
            'fecha_nacimiento' => '1990-01-01',
        ]);

        $csv = $this->actingAs($this->crearSupervisor($proyecto))->get($this->url($proyecto))->assertOk()->streamedContent();
        $fila = $this->filasDe($csv)[0];

        $this->assertSame('2026-09-07 20:30:00', $fila['creada_en']);
        $this->assertSame('1990-01-01', $fila['fecha_nacimiento']);
        $this->assertSame('1', $fila['total_casos']);
    }

    /**
     * Un rol acotado por cartera (F22) no se lleva el padrón entero.
     *
     * Una persona no pertenece a una cartera, sus casos sí: el recorte es
     * «tener al menos un caso en mis carteras». Sin esto, el supervisor al que
     * la pantalla le esconde una cartera se llevaba en el CSV a todas sus
     * personas, con nombre e identificación.
     */
    public function test_el_recorte_por_cartera_del_rol_gobierna_el_padron(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $permitida = $this->crearCarteraEn($proyecto);
        $vetada = $this->crearCarteraEn($proyecto);

        $deLaPermitida = $this->crearPersonaEn($proyecto, '6100000001');
        $deLaVetada = $this->crearPersonaEn($proyecto, '6100000002');
        $this->crearPersonaEn($proyecto, '6100000003');

        $this->crearCasoEn($proyecto, ['cartera' => $permitida, 'persona' => $deLaPermitida]);
        $this->crearCasoEn($proyecto, ['cartera' => $vetada, 'persona' => $deLaVetada]);

        $supervisor = $this->crearSupervisor($proyecto);
        DB::table('usuario_proyecto_rol_cartera')->insert([
            'usuario_id' => $supervisor->id,
            'proyecto_id' => $proyecto->id,
            'rol_id' => (int) DB::table('roles')->where('codigo', 'SUPERVISOR')->value('id'),
            'cartera_id' => $permitida->id,
        ]);

        $csv = $this->actingAs($supervisor)->get($this->url($proyecto))->assertOk()->streamedContent();

        $this->assertSame(
            ['6100000001'],
            array_column($this->filasDe($csv), 'identificacion'),
            'Ni la persona de la cartera vetada, ni la que no tiene ningún caso.',
        );
    }

    /** @param  array<string, string>  $filtros */
    private function url(stdClass $proyecto, array $filtros = []): string
    {
        return route('proyectos.personas.exportar', ['proyecto_id' => (int) $proyecto->id] + $filtros);
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

    private function crearPersonaOperativaEn(stdClass $proyecto, ?string $identificacion = null): stdClass
    {
        $persona = $this->crearPersonaEn($proyecto, $identificacion);
        $this->crearCasoEn($proyecto, ['persona' => $persona]);

        return $persona;
    }
}
