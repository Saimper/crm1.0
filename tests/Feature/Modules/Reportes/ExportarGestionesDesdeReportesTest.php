<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Reportes;

use App\Modules\Reportes\Infrastructure\Http\Livewire\DashboardOperativo;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * Reportes operativos es donde el supervisor mira las gestiones, así que es
 * desde donde se exportan: con el rango que tiene puesto, o con dos fechas.
 */
final class ExportarGestionesDesdeReportesTest extends TestCase
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

    public function test_el_enlace_de_exportar_lleva_el_rango_activo(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearSupervisor($proyecto));

        $urlSemana = route('proyectos.gestiones.exportar', ['proyecto_id' => $proyecto->id, 'rango' => 'semana']);

        Livewire::test(DashboardOperativo::class)
            ->set('rango', 'semana')
            ->assertSee($urlSemana, false)
            ->assertSee('name="desde"', false)
            ->assertSee('name="hasta"', false);
    }

    public function test_quien_no_puede_exportar_no_ve_el_enlace(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);

        // Un supervisor al que el cliente le retiró la exportación sigue viendo
        // el informe, pero sin la puerta de salida de los datos.
        DB::table('rol_permiso')
            ->where('rol_id', (int) DB::table('roles')->where('codigo', 'SUPERVISOR')->value('id'))
            ->where('permiso_id', (int) DB::table('permisos')->where('codigo', 'gestiones.exportar')->value('id'))
            ->delete();

        $this->actingAs($this->crearSupervisor($proyecto));

        Livewire::test(DashboardOperativo::class)
            ->assertOk()
            ->assertDontSee(route('proyectos.gestiones.exportar', ['proyecto_id' => $proyecto->id]), false);
    }

    public function test_el_rango_de_la_pantalla_se_corta_en_la_zona_del_cliente(): void
    {
        $mandante = $this->crearMandante();
        DB::table('mandantes')->where('id', $mandante->id)->update(['zona_horaria' => 'America/Panama']);
        $proyecto = $this->crearProyectoCobranza($mandante);
        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearSupervisor($proyecto));

        // 02:00 UTC del 8 = 21:00 del 7 en Panamá. Una gestión a las 12:00 UTC
        // del 7 es de «hoy» para el cliente aunque para el servidor sea de ayer.
        Carbon::setTestNow(Carbon::parse('2026-09-08 02:00:00', 'UTC'));
        $this->insertarGestion($proyecto, '2026-09-07 12:00:00');

        Livewire::test(DashboardOperativo::class)
            ->set('rango', 'hoy')
            ->assertViewHas('totalGestiones', 1)
            ->assertViewHas('etiquetaRango', __('reportes.range_today'));
    }

    public function test_un_rango_desconocido_cuenta_como_hoy(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearSupervisor($proyecto));

        Livewire::test(DashboardOperativo::class)
            ->set('rango', 'lo-que-sea')
            ->assertOk()
            ->assertViewHas('etiquetaRango', __('reportes.range_today'))
            ->assertSee(route('proyectos.gestiones.exportar', ['proyecto_id' => $proyecto->id, 'rango' => 'hoy']), false);
    }

    private function insertarGestion(\stdClass $proyecto, string $creadaEn): void
    {
        $persona = $this->crearPersonaEn($proyecto);
        $casoId = $this->crearCasoEn($proyecto, ['persona' => $persona]);
        $cascada = $this->crearCascadaGestionEn($proyecto);

        DB::table('gestiones')->insert([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'caso_id' => $casoId,
            'persona_id' => $persona->id,
            'canal_id' => $cascada['canal_id'],
            'tipo_gestion_id' => $cascada['tipo_gestion_id'],
            'resultado_id' => $cascada['resultado_id'],
            'usuario_id' => $this->crearGestor($proyecto)->id,
            'creada_en' => $creadaEn,
            'actualizada_en' => $creadaEn,
        ]);
    }
}
