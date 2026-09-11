<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Compromisos;

use App\Models\User;
use App\Modules\Compromisos\Infrastructure\Http\Livewire\ListadoCompromisos;
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
 * La descarga de compromisos lleva los filtros del listado, el monto de la
 * promesa, el «hoy» del cliente en los vencimientos, permiso propio y huella.
 */
final class ExportarCompromisosTest extends TestCase
{
    use EscenarioMultiMandante;
    use InsertaCti;
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

    public function test_combined_roles_scope_listing_and_export_to_roles_that_grant_each_permission(): void
    {
        $project = $this->crearProyectoCobranza();
        $allowed = $this->crearCarteraEn($project);
        $denied = $this->crearCarteraEn($project);
        $own = $this->crearCompromisoEn($project, cartera: $allowed);
        $this->crearCompromisoEn($project, cartera: $denied);
        $user = $this->crearSupervisor($project);
        $gestorRole = (int) DB::table('roles')->where('codigo', 'GESTOR')->value('id');
        $viewPermission = (int) DB::table('permisos')->where('codigo', 'compromisos.ver')->value('id');
        DB::table('usuario_proyecto_rol_cartera')->insert([
            'usuario_id' => $user->id, 'proyecto_id' => $project->id,
            'rol_id' => DB::table('roles')->where('codigo', 'SUPERVISOR')->value('id'), 'cartera_id' => $allowed->id,
        ]);
        DB::table('usuario_proyecto_rol')->insert([
            'usuario_id' => $user->id, 'proyecto_id' => $project->id, 'rol_id' => $gestorRole, 'activo' => true,
        ]);
        // The additional unrestricted role grants neither viewing nor exporting here.
        DB::table('rol_proyecto_permiso')->insert([
            'proyecto_id' => $project->id, 'rol_id' => $gestorRole, 'permiso_id' => $viewPermission, 'permitido' => false,
        ]);
        $this->activarProyecto($project);
        $this->actingAs($user);
        $list = Livewire::test(ListadoCompromisos::class);
        $this->assertSame(1, $list->viewData('compromisos')->total());
        $this->assertSame(1, $list->viewData('resumen')['pendientes']);
        $csv = $this->get($this->url($project))->assertOk()->streamedContent();
        $this->assertSame([$this->publicIdDe($own)], array_column($this->filasDe($csv), 'compromiso_public_id'));

        // Restoring broad viewing must not widen the separate export permission.
        DB::table('rol_proyecto_permiso')->where('proyecto_id', $project->id)
            ->where('rol_id', $gestorRole)->where('permiso_id', $viewPermission)->delete();
        User::olvidarPermisosCacheados();
        $list = Livewire::test(ListadoCompromisos::class);
        $this->assertSame(2, $list->viewData('compromisos')->total());
        $this->assertSame(2, $list->viewData('resumen')['pendientes']);
        $csv = $this->get($this->url($project))->assertOk()->streamedContent();
        $this->assertSame([$this->publicIdDe($own)], array_column($this->filasDe($csv), 'compromiso_public_id'));
    }

    public function test_operational_list_summary_filters_and_csv_share_active_portfolio_scope(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 17:00:00', 'UTC'));
        $project = $this->crearProyectoCobranza();
        $foreignProject = $this->crearProyectoCobranza();
        $active = $this->crearCarteraEn($project);
        $inactive = $this->crearCarteraEn($project);
        $deleted = $this->crearCarteraEn($project);
        $denied = $this->crearCarteraEn($project);
        $allowedIds = [];
        $historicalIds = [];
        foreach ([$active, $inactive, $deleted, $denied] as $portfolio) {
            foreach ([['pendiente', '2026-09-09'], ['pendiente', '2026-09-11'], ['cumplido', '2026-09-09'], ['roto', '2026-09-09']] as [$state, $due]) {
                $id = $this->crearCompromisoEn($project, $state, $due, cartera: $portfolio);
                if ($portfolio->id === $active->id) {
                    $allowedIds[] = $this->publicIdDe($id);
                } elseif ($portfolio->id !== $denied->id) {
                    $historicalIds[] = $id;
                }
            }
        }
        $this->crearCompromisoEn($foreignProject, 'pendiente', '2026-09-09');
        DB::table('carteras')->where('id', $inactive->id)->update(['activo' => false]);
        // Soft deletion must exclude the portfolio even if its active flag remains true.
        DB::table('carteras')->where('id', $deleted->id)->update(['eliminada_en' => now()]);
        $supervisor = $this->crearSupervisor($project);
        foreach ([$active, $inactive, $deleted] as $portfolio) {
            DB::table('usuario_proyecto_rol_cartera')->insert([
                'usuario_id' => $supervisor->id, 'proyecto_id' => $project->id,
                'rol_id' => DB::table('roles')->where('codigo', 'SUPERVISOR')->value('id'), 'cartera_id' => $portfolio->id,
            ]);
        }
        $this->activarProyecto($project);
        $this->actingAs($supervisor);
        $list = Livewire::test(ListadoCompromisos::class);
        $expectedSummary = ['pendientes' => 2, 'vencidos' => 1, 'cumplidos' => 1, 'rotos' => 1];
        $this->assertSame(4, $list->viewData('compromisos')->total());
        $this->assertSame($expectedSummary, $list->viewData('resumen'));
        $this->assertEqualsCanonicalizing($allowedIds, collect($list->viewData('compromisos')->items())->pluck('public_id')->all());
        $csv = $this->get($this->url($project))->assertOk()->streamedContent();
        $this->assertEqualsCanonicalizing($allowedIds, array_column($this->filasDe($csv), 'compromiso_public_id'));

        $list->set('vencimiento', 'vencidos');
        $this->assertSame(1, $list->viewData('compromisos')->total());
        $this->assertSame($expectedSummary, $list->viewData('resumen'), 'The overview keeps the whole authorized operational scope when a list filter changes.');
        $filteredCsv = $this->get($this->url($project, ['venc' => 'vencidos']))->assertOk()->streamedContent();
        $this->assertSame([$allowedIds[0]], array_column($this->filasDe($filteredCsv), 'compromiso_public_id'));

        DB::table('carteras')->where('id', $active->id)->update(['activo' => false]);
        $list->call('limpiarFiltros');
        $this->assertSame(0, $list->viewData('compromisos')->total());
        $this->assertSame(['pendientes' => 0, 'vencidos' => 0, 'cumplidos' => 0, 'rotos' => 0], $list->viewData('resumen'));
        $emptyCsv = $this->get($this->url($project))->assertOk()->streamedContent();
        $this->assertSame([], $this->filasDe($emptyCsv));
        $this->assertSame(count($historicalIds), DB::table('compromisos')->whereIn('id', $historicalIds)->whereNull('eliminada_en')->count());
        $this->assertDatabaseCount('compromisos', 17);
    }

    public function test_el_csv_solo_trae_compromisos_del_proyecto_activo(): void
    {
        $mandante = $this->crearMandante();
        $proyectoA = $this->crearProyectoCobranza($mandante);
        $proyectoB = $this->crearProyectoCobranza($mandante);
        $proyectoAjeno = $this->crearProyectoCobranza($this->crearMandante());

        $propio = $this->crearCompromisoEn($proyectoA);
        $delHermano = $this->crearCompromisoEn($proyectoB);
        $delAjeno = $this->crearCompromisoEn($proyectoAjeno);

        $csv = $this->actingAs($this->crearSupervisor($proyectoA))
            ->get($this->url($proyectoA))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->streamedContent();

        $ids = array_column($this->filasDe($csv), 'compromiso_public_id');

        $this->assertSame([$this->publicIdDe($propio)], $ids);
        $this->assertNotContains($this->publicIdDe($delHermano), $ids);
        $this->assertNotContains($this->publicIdDe($delAjeno), $ids);
    }

    public function test_el_vencimiento_se_decide_con_el_dia_del_cliente(): void
    {
        $mandante = $this->crearMandante();
        DB::table('mandantes')->where('id', $mandante->id)->update(['zona_horaria' => 'America/Panama']);
        $proyecto = $this->crearProyectoCobranza($mandante);

        // 02:00 UTC del 8 son las 21:00 del 7 en Panamá: para el cliente aún es
        // día 7, y una promesa que vence el 7 sigue vigente. Con el corte en
        // UTC ya estaba «vencida» cinco horas antes de que se acabara el plazo.
        Carbon::setTestNow(Carbon::parse('2026-09-08 02:00:00', 'UTC'));
        $venceHoy = $this->crearCompromisoEn($proyecto, 'pendiente', '2026-09-07');
        $vencioAyer = $this->crearCompromisoEn($proyecto, 'pendiente', '2026-09-06');

        $supervisor = $this->crearSupervisor($proyecto);

        $vencidos = $this->actingAs($supervisor)->get($this->url($proyecto, ['venc' => 'vencidos']))->assertOk()->streamedContent();
        $vigentes = $this->actingAs($supervisor)->get($this->url($proyecto, ['venc' => 'vigentes']))->assertOk()->streamedContent();

        $this->assertSame([$this->publicIdDe($vencioAyer)], array_column($this->filasDe($vencidos), 'compromiso_public_id'));
        $this->assertSame([$this->publicIdDe($venceHoy)], array_column($this->filasDe($vigentes), 'compromiso_public_id'));
    }

    public function test_matriz_de_permisos(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();
        $proyecto = $a['proyecto'];

        $this->actingAs($a['supervisor'])->get($this->url($proyecto))->assertOk();
        $this->actingAs($a['adminMandante'])->get($this->url($proyecto))->assertOk();
        $this->actingAs($b['adminMandante'])->get($this->url($proyecto))->assertForbidden();
        $this->actingAs($a['gestor'])->get($this->url($proyecto))->assertForbidden();

        // Los compromisos son actividad: el auditor sí se los lleva.
        $this->actingAs($this->crearAuditor($proyecto))->get($this->url($proyecto))->assertOk();
    }

    public function test_el_csv_devuelve_las_mismas_filas_que_el_listado_con_los_mismos_filtros(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->crearCompromisoEn($proyecto, 'pendiente');
        $this->crearCompromisoEn($proyecto, 'pendiente');
        $this->crearCompromisoEn($proyecto, 'cumplido');
        $this->crearCompromisoEn($proyecto, 'pendiente', null, 'resolucion_ticket');

        $supervisor = $this->crearSupervisor($proyecto);
        $this->activarProyecto($proyecto);
        $this->actingAs($supervisor);

        $enPantalla = Livewire::test(ListadoCompromisos::class)
            ->set('estado', 'pendiente')
            ->set('tipoCompromiso', 'promesa_pago')
            ->viewData('compromisos');
        $idsPantalla = array_map(static fn (stdClass $c): string => (string) $c->public_id, iterator_to_array($enPantalla));

        $csv = $this->get($this->url($proyecto, ['estado' => 'pendiente', 'tipo' => 'promesa_pago']))->assertOk()->streamedContent();
        $idsCsv = array_column($this->filasDe($csv), 'compromiso_public_id');

        sort($idsPantalla);
        sort($idsCsv);

        $this->assertCount(2, $idsPantalla, 'El escenario no está filtrando nada.');
        $this->assertSame($idsPantalla, $idsCsv);
    }

    public function test_un_parametro_con_forma_de_array_no_rompe_la_descarga(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->crearCompromisoEn($proyecto);

        $this->actingAs($this->crearSupervisor($proyecto))
            ->get($this->url($proyecto).'?estado[]=x&venc[]=1&tipo[]=2')
            ->assertOk();
    }

    public function test_la_exportacion_deja_huella_en_auditoria(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->crearCompromisoEn($proyecto, 'roto');

        $this->actingAs($this->crearAuditor($proyecto))
            ->get($this->url($proyecto, ['estado' => 'roto']))
            ->assertOk()
            ->streamedContent();

        $huella = DB::table('auditorias')->where('evento', 'exportado')->first();

        $this->assertNotNull($huella);
        $this->assertSame('compromisos', $huella->entidad_tipo);
        $this->assertSame((int) $proyecto->id, (int) $huella->proyecto_id);

        $cambios = json_decode((string) $huella->cambios, true);
        $this->assertSame(['estado' => 'roto'], $cambios['filtros']['despues']);
        $this->assertSame(1, $cambios['total_filas']['despues']);
    }

    public function test_una_promesa_de_pago_sale_con_su_monto_y_su_tipo_de_pago(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $compromisoId = $this->insertarCompromisoPromesaPagoConTipoPago($proyecto, $this->crearTipoPagoEn($proyecto));

        $csv = $this->actingAs($this->crearSupervisor($proyecto))->get($this->url($proyecto))->assertOk()->streamedContent();
        $filas = $this->filasDe($csv);

        $this->assertCount(1, $filas);
        $this->assertSame($this->publicIdDe($compromisoId), $filas[0]['compromiso_public_id']);
        $this->assertSame('500.000', $filas[0]['monto'], 'El monto de la promesa es lo primero que pide un supervisor.');
        $this->assertSame('USD', $filas[0]['moneda']);
        $this->assertSame('Tipo de pago', $filas[0]['tipo_pago'], 'El nombre del catálogo, no el id.');
    }

    /**
     * Un compromiso del proyecto con su caso propio, sin fila CTI: lo que se
     * prueba aquí es el recorte y los filtros de la tabla base.
     */
    /**
     * Un supervisor acotado a ciertas carteras (F22) descarga sólo los
     * compromisos de los casos de esas carteras.
     */
    public function test_el_recorte_por_cartera_del_rol_gobierna_la_descarga(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $permitida = $this->crearCarteraEn($proyecto);
        $vetada = $this->crearCarteraEn($proyecto);

        $delPermitido = $this->crearCompromisoEn($proyecto, cartera: $permitida);
        $this->crearCompromisoEn($proyecto, cartera: $vetada);

        $supervisor = $this->crearSupervisor($proyecto);
        DB::table('usuario_proyecto_rol_cartera')->insert([
            'usuario_id' => $supervisor->id,
            'proyecto_id' => $proyecto->id,
            'rol_id' => (int) DB::table('roles')->where('codigo', 'SUPERVISOR')->value('id'),
            'cartera_id' => $permitida->id,
        ]);

        $csv = $this->actingAs($supervisor)->get($this->url($proyecto))->assertOk()->streamedContent();

        $this->assertSame(
            [$this->publicIdDe($delPermitido)],
            array_column($this->filasDe($csv), 'compromiso_public_id'),
        );
    }

    private function crearCompromisoEn(
        stdClass $proyecto,
        string $estado = 'pendiente',
        ?string $fechaVencimiento = null,
        string $tipoCompromiso = 'promesa_pago',
        ?stdClass $cartera = null,
    ): int {
        $casoId = $this->crearCasoEn($proyecto, $cartera === null ? [] : ['cartera' => $cartera]);
        $usuario = $this->crearGestor($proyecto);
        $ahora = Carbon::now();

        return (int) DB::table('compromisos')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'caso_id' => $casoId,
            'tipo_compromiso' => $tipoCompromiso,
            'estado' => $estado,
            'fecha_vencimiento' => $fechaVencimiento ?? Carbon::today()->addWeek()->toDateString(),
            'fecha_resolucion' => $estado === 'pendiente' ? null : Carbon::today()->toDateString(),
            'usuario_id' => $usuario->id,
            'creada_en' => $ahora,
            'actualizada_en' => $ahora,
        ]);
    }

    private function publicIdDe(int $compromisoId): string
    {
        return (string) DB::table('compromisos')->where('id', $compromisoId)->value('public_id');
    }

    /** @param  array<string, string>  $filtros */
    private function url(stdClass $proyecto, array $filtros = []): string
    {
        return route('proyectos.compromisos.exportar', ['proyecto_id' => (int) $proyecto->id] + $filtros);
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
