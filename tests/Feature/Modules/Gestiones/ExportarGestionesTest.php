<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Gestiones;

use App\Models\User;
use App\Modules\Gestiones\Application\DTOs\RegistrarGestionInput;
use App\Modules\Gestiones\Application\UseCases\RegistrarGestion;
use App\Modules\Gestiones\Domain\ValueObjects\DuracionSegundos;
use Database\Seeders\DatabaseSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;
use Tests\Support\EscenarioMultiMandante;
use Tests\TestCase;

/**
 * La descarga de gestiones se corta en el calendario del cliente —con el
 * mismo rango que Reportes operativos o con dos fechas—, tiene tope, ignora
 * usuarios que no son del proyecto, exige permiso propio y deja huella.
 */
final class ExportarGestionesTest extends TestCase
{
    use EscenarioMultiMandante;
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

    public function test_operational_csv_excludes_inactive_deleted_and_unauthorized_portfolios_without_removing_history(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 17:00:00', 'UTC'));
        $project = $this->crearProyectoCobranza();
        $foreignProject = $this->crearProyectoCobranza();
        $active = $this->crearCarteraEn($project);
        $inactive = $this->crearCarteraEn($project);
        $deleted = $this->crearCarteraEn($project);
        $denied = $this->crearCarteraEn($project);
        $operator = $this->crearGestor($project);
        $allowed = $this->registrarGestionEn($project, usuario: $operator, cartera: $active);
        $historical = [
            $this->registrarGestionEn($project, usuario: $operator, cartera: $inactive),
            $this->registrarGestionEn($project, usuario: $operator, cartera: $deleted),
        ];
        $this->registrarGestionEn($project, usuario: $operator, cartera: $denied);
        $this->registrarGestionEn($foreignProject);
        DB::table('carteras')->where('id', $inactive->id)->update(['activo' => false]);
        DB::table('carteras')->where('id', $deleted->id)->update(['eliminada_en' => now()]);
        $supervisor = $this->crearSupervisor($project);
        foreach ([$active, $inactive, $deleted] as $portfolio) {
            DB::table('usuario_proyecto_rol_cartera')->insert([
                'usuario_id' => $supervisor->id, 'proyecto_id' => $project->id,
                'rol_id' => DB::table('roles')->where('codigo', 'SUPERVISOR')->value('id'), 'cartera_id' => $portfolio->id,
            ]);
        }
        $this->actingAs($supervisor);
        foreach ([['rango' => 'hoy'], ['desde' => '2026-09-10', 'hasta' => '2026-09-10', 'usuario_id' => (string) $operator->id]] as $filters) {
            $csv = $this->get($this->url($project, $filters))->assertOk()->streamedContent();
            $this->assertSame([$allowed], array_column($this->filasDe($csv), 'gestion_public_id'));
        }
        DB::table('carteras')->where('id', $active->id)->update(['activo' => false]);
        $emptyCsv = $this->get($this->url($project, ['rango' => 'hoy']))->assertOk()->streamedContent();
        $this->assertSame([], $this->filasDe($emptyCsv));
        $this->assertSame(2, DB::table('gestiones')->whereIn('public_id', $historical)->whereNull('eliminada_en')->count());
        $this->assertDatabaseCount('gestiones', 5);
    }

    public function test_el_csv_solo_trae_gestiones_del_proyecto_activo(): void
    {
        $mandante = $this->crearMandante();
        $proyectoA = $this->crearProyectoCobranza($mandante);
        $proyectoB = $this->crearProyectoCobranza($mandante);
        $proyectoAjeno = $this->crearProyectoCobranza($this->crearMandante());

        $propia = $this->registrarGestionEn($proyectoA, notas: 'nota propia');
        $this->registrarGestionEn($proyectoB, notas: 'nota del hermano');
        $this->registrarGestionEn($proyectoAjeno, notas: 'nota ajena');

        $csv = $this->actingAs($this->crearSupervisor($proyectoA))
            ->get($this->url($proyectoA, ['rango' => 'hoy']))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->streamedContent();

        $filas = $this->filasDe($csv);

        $this->assertSame([$propia], array_column($filas, 'gestion_public_id'));
        $this->assertStringNotContainsString('nota del hermano', $csv);
        $this->assertStringNotContainsString('nota ajena', $csv);
    }

    public function test_matriz_de_permisos(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();
        $proyecto = $a['proyecto'];

        $this->actingAs($a['supervisor'])->get($this->url($proyecto))->assertOk();
        $this->actingAs($a['adminMandante'])->get($this->url($proyecto))->assertOk();
        $this->actingAs($b['adminMandante'])->get($this->url($proyecto))->assertForbidden();
        $this->actingAs($a['gestor'])->get($this->url($proyecto))->assertForbidden();

        // Las gestiones son actividad: el auditor sí se las lleva.
        $this->actingAs($this->crearAuditor($proyecto))->get($this->url($proyecto))->assertOk();
    }

    public function test_desde_y_hasta_se_cortan_en_el_calendario_del_cliente(): void
    {
        $mandante = $this->crearMandante();
        DB::table('mandantes')->where('id', $mandante->id)->update(['zona_horaria' => 'America/Panama']);
        $proyecto = $this->crearProyectoCobranza($mandante);

        // 01:30 UTC del 8 son las 20:30 del 7 en Panamá: es una gestión DEL 7.
        Carbon::setTestNow(Carbon::parse('2026-09-08 03:00:00', 'UTC'));
        $deLaTarde = $this->registrarGestionEn($proyecto, creadaEn: '2026-09-08 01:30:00');

        $supervisor = $this->crearSupervisor($proyecto);

        $dia7 = $this->actingAs($supervisor)->get($this->url($proyecto, ['desde' => '2026-09-07', 'hasta' => '2026-09-07']))->assertOk()->streamedContent();
        $dia8 = $this->actingAs($supervisor)->get($this->url($proyecto, ['desde' => '2026-09-08', 'hasta' => '2026-09-08']))->assertOk()->streamedContent();

        $this->assertSame([$deLaTarde], array_column($this->filasDe($dia7), 'gestion_public_id'), 'Cortando en UTC esta gestión se iría al día 8.');
        $this->assertSame([], $this->filasDe($dia8));

        // Y la hora escrita en el CSV es la del cliente.
        $this->assertSame('2026-09-07 20:30:00', $this->filasDe($dia7)[0]['creada_en']);
    }

    public function test_el_rango_preestablecido_es_el_mismo_que_el_de_reportes_operativos(): void
    {
        $mandante = $this->crearMandante();
        DB::table('mandantes')->where('id', $mandante->id)->update(['zona_horaria' => 'America/Panama']);
        $proyecto = $this->crearProyectoCobranza($mandante);

        // 02:00 UTC del 8 = 21:00 del 7 en Panamá. Una gestión de las 12:00 UTC
        // del 7 (07:00 en Panamá) es de «hoy» para el cliente; para UTC ya era ayer.
        Carbon::setTestNow(Carbon::parse('2026-09-08 02:00:00', 'UTC'));
        $deHoy = $this->registrarGestionEn($proyecto, creadaEn: '2026-09-07 12:00:00');

        $csv = $this->actingAs($this->crearSupervisor($proyecto))
            ->get($this->url($proyecto, ['rango' => 'hoy']))
            ->assertOk()
            ->streamedContent();

        $this->assertSame([$deHoy], array_column($this->filasDe($csv), 'gestion_public_id'));
    }

    public function test_una_ventana_de_mas_de_92_dias_vuelve_al_formulario_con_el_motivo(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $supervisor = $this->crearSupervisor($proyecto);

        // 1 de enero a 2 de abril son 92 días justos: pasa. Un día más, no.
        $this->actingAs($supervisor)->get($this->url($proyecto, ['desde' => '2026-01-01', 'hasta' => '2026-04-02']))->assertOk();

        $this->actingAs($supervisor)
            ->get($this->url($proyecto, ['desde' => '2026-01-01', 'hasta' => '2026-04-03']))
            ->assertRedirect(route('proyectos.reportes.operativos', ['proyecto_id' => (int) $proyecto->id]))
            ->assertSessionHasErrors('exportar');
    }

    public function test_desde_posterior_a_hasta_o_fechas_ilegibles_vuelven_al_formulario(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $supervisor = $this->crearSupervisor($proyecto);
        $destino = route('proyectos.reportes.operativos', ['proyecto_id' => (int) $proyecto->id]);

        foreach ([
            ['desde' => '2026-09-08', 'hasta' => '2026-09-07'],
            ['desde' => '2026-09-07'],
            ['desde' => 'ayer', 'hasta' => 'hoy'],
        ] as $ventana) {
            $this->actingAs($supervisor)
                ->get($this->url($proyecto, $ventana))
                ->assertRedirect($destino)
                ->assertSessionHasErrors('exportar');
        }
    }

    /**
     * Quien pide JSON no tiene formulario al que volver: para él sigue siendo
     * un 422. Un 422 en el navegador pintaba la página de Symfony, en inglés y
     * sin el motivo, porque la aplicación no tiene vista para ese código.
     */
    public function test_para_una_peticion_json_la_ventana_inadmisible_sigue_siendo_422(): void
    {
        $proyecto = $this->crearProyectoCobranza();

        $this->actingAs($this->crearSupervisor($proyecto))
            ->getJson($this->url($proyecto, ['desde' => '2026-01-01', 'hasta' => '2026-12-31']))
            ->assertStatus(422);
    }

    /**
     * El filtro por usuario se aplica siempre, venga quien venga en la URL.
     *
     * Antes se comprobaba si ese usuario tenía rol en el proyecto y, si no, se
     * ignoraba el filtro: un id de otro cliente devolvía la actividad ENTERA
     * del proyecto en un fichero cuyo nombre y cuya huella decían «de un
     * usuario». Como el recorte por proyecto va delante, un id ajeno sólo
     * puede devolver cero filas, que es la respuesta honesta.
     */
    public function test_el_filtro_por_usuario_se_aplica_aunque_el_usuario_no_sea_del_proyecto(): void
    {
        $mandante = $this->crearMandante();
        $proyecto = $this->crearProyectoCobranza($mandante);
        $otroProyecto = $this->crearProyectoCobranza($mandante);

        $gestorUno = $this->crearGestor($proyecto);
        $gestorAjeno = $this->crearGestor($otroProyecto);

        $deUno = $this->registrarGestionEn($proyecto, usuario: $gestorUno);
        $this->registrarGestionEn($proyecto);

        $supervisor = $this->crearSupervisor($proyecto);

        $conAjeno = $this->actingAs($supervisor)->get($this->url($proyecto, ['rango' => 'hoy', 'usuario_id' => (string) $gestorAjeno->id]))->assertOk()->streamedContent();
        $conUno = $this->actingAs($supervisor)->get($this->url($proyecto, ['rango' => 'hoy', 'usuario_id' => (string) $gestorUno->id]))->assertOk()->streamedContent();

        $this->assertSame([], $this->filasDe($conAjeno), 'Un usuario de otro proyecto no tiene gestiones aquí: cero filas, no todas.');
        $this->assertSame([$deUno], array_column($this->filasDe($conUno), 'gestion_public_id'));

        // Y la huella dice con qué usuario se pidió, para que el recuento cuadre.
        $huella = DB::table('auditorias')->where('evento', 'exportado')->orderByDesc('id')->first();
        $this->assertNotNull($huella);
        $filtros = json_decode((string) $huella->cambios, true)['filtros']['despues'];
        $this->assertSame((string) $gestorUno->id, $filtros['usuario_id']);
    }

    /**
     * Un supervisor con el rol acotado a ciertas carteras (F22) descarga sólo
     * las gestiones de esas carteras: sin esto, la descarga era la puerta de
     * atrás a la cartera que la pantalla le esconde.
     */
    public function test_el_recorte_por_cartera_del_rol_gobierna_la_descarga(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $permitida = $this->crearCarteraEn($proyecto);
        $vetada = $this->crearCarteraEn($proyecto);

        $deLaPermitida = $this->registrarGestionEn($proyecto, cartera: $permitida);
        $this->registrarGestionEn($proyecto, cartera: $vetada);

        $supervisor = $this->crearSupervisor($proyecto);
        DB::table('usuario_proyecto_rol_cartera')->insert([
            'usuario_id' => $supervisor->id,
            'proyecto_id' => $proyecto->id,
            'rol_id' => (int) DB::table('roles')->where('codigo', 'SUPERVISOR')->value('id'),
            'cartera_id' => $permitida->id,
        ]);
        // An additional unrestricted role without export permission must not widen the CSV.
        DB::table('usuario_proyecto_rol')->insert([
            'usuario_id' => $supervisor->id,
            'proyecto_id' => $proyecto->id,
            'rol_id' => DB::table('roles')->where('codigo', 'GESTOR')->value('id'),
            'activo' => true,
        ]);

        $csv = $this->actingAs($supervisor)->get($this->url($proyecto, ['rango' => 'hoy']))->assertOk()->streamedContent();

        $this->assertSame([$deLaPermitida], array_column($this->filasDe($csv), 'gestion_public_id'));
    }

    public function test_un_parametro_con_forma_de_array_no_rompe_la_descarga(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->registrarGestionEn($proyecto);

        $this->actingAs($this->crearSupervisor($proyecto))
            ->get($this->url($proyecto).'?rango[]=x&usuario_id[]=1&desde[]=2')
            ->assertOk();
    }

    public function test_la_exportacion_deja_huella_en_auditoria_con_su_recorte(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->registrarGestionEn($proyecto);
        $hoy = Carbon::now()->toDateString();

        $this->actingAs($this->crearAuditor($proyecto))
            ->get($this->url($proyecto, ['desde' => $hoy, 'hasta' => $hoy]))
            ->assertOk()
            ->streamedContent();

        $huella = DB::table('auditorias')->where('evento', 'exportado')->first();

        $this->assertNotNull($huella);
        $this->assertSame('gestiones', $huella->entidad_tipo);
        $this->assertSame((int) $proyecto->id, (int) $huella->proyecto_id);

        $cambios = json_decode((string) $huella->cambios, true);
        $this->assertSame(['desde' => $hoy, 'hasta' => $hoy], $cambios['filtros']['despues']);
        $this->assertSame(1, $cambios['total_filas']['despues']);
    }

    public function test_las_columnas_llevan_nombres_de_catalogo_y_no_ids(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->registrarGestionEn($proyecto, notas: 'la nota', duracion: 120);

        $csv = $this->actingAs($this->crearSupervisor($proyecto))->get($this->url($proyecto))->assertOk()->streamedContent();
        $fila = $this->filasDe($csv)[0];

        $this->assertStringStartsWith('Tipo ', $fila['tipo_gestion']);
        $this->assertStringStartsWith('Resultado ', $fila['resultado']);
        $this->assertStringStartsWith('Motivo ', $fila['motivo_no_contacto']);
        $this->assertStringStartsWith('Causa ', $fila['causa']);
        $this->assertSame('sí', $fila['es_contacto_efectivo']);
        $this->assertSame('120', $fila['duracion_segundos']);
        $this->assertSame('la nota', $fila['notas']);
        $this->assertSame('Test Persona', $fila['nombre_persona']);
    }

    /**
     * Una gestión registrada por el caso de uso, con la hora que se le diga.
     * Devuelve su `public_id`, que es lo que el CSV expone.
     */
    private function registrarGestionEn(
        stdClass $proyecto,
        ?User $usuario = null,
        ?string $creadaEn = null,
        ?string $notas = null,
        ?int $duracion = null,
        ?stdClass $cartera = null,
    ): string {
        $persona = $this->crearPersonaEn($proyecto);
        $opciones = ['persona' => $persona];
        if ($cartera !== null) {
            $opciones['cartera'] = $cartera;
        }
        $casoId = $this->crearCasoEn($proyecto, $opciones);
        $cascada = $this->crearCascadaGestionEn($proyecto);
        $usuario ??= $this->crearGestor($proyecto);
        $publicId = (string) Str::ulid();

        $this->app->make(RegistrarGestion::class)->execute(new RegistrarGestionInput(
            publicId: $publicId,
            proyectoId: (int) $proyecto->id,
            casoId: $casoId,
            personaId: (int) $persona->id,
            contactoId: null,
            canalId: $cascada['canal_id'],
            tipoGestionId: $cascada['tipo_gestion_id'],
            resultadoId: $cascada['resultado_id'],
            motivoNoContactoId: null,
            causaId: $cascada['causa_id'],
            usuarioId: (int) $usuario->id,
            notas: $notas,
            duracion: $duracion === null ? null : new DuracionSegundos($duracion),
            creadaEn: new DateTimeImmutable($creadaEn ?? Carbon::now('UTC')->toDateTimeString()),
        ));

        // Historical rows may predate the explicit no-contact result flag.
        DB::table('gestiones')->where('public_id', $publicId)
            ->update(['motivo_no_contacto_id' => $cascada['motivo_no_contacto_id']]);

        return $publicId;
    }

    /** @param  array<string, string>  $filtros */
    private function url(stdClass $proyecto, array $filtros = []): string
    {
        return route('proyectos.gestiones.exportar', ['proyecto_id' => (int) $proyecto->id] + $filtros);
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
