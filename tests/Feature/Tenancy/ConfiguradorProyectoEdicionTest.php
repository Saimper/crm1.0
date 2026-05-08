<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Modules\Tenancy\Domain\ConfiguracionProyecto\PasoConfiguracion;
use App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\PasoResumen;
use App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorProyecto;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\ProyectoModel;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class ConfiguradorProyectoEdicionTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_global_puede_acceder_a_modo_edicion(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $admin = $this->crearAdminGlobal();

        $this->actingAs($admin)
            ->get(route('admin.proyectos.configurar.editar', ['proyecto' => $proyecto->public_id]))
            ->assertStatus(200)
            ->assertSee('Editar configuración');
    }

    public function test_supervisor_no_accede_a_modo_edicion(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $supervisor = $this->crearSupervisor($proyecto);

        $this->actingAs($supervisor)
            ->get(route('admin.proyectos.configurar.editar', ['proyecto' => $proyecto->public_id]))
            ->assertStatus(403);
    }

    public function test_en_modo_edicion_todos_los_pasos_son_navegables_sin_validar_dependencia(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $this->actingAs($this->crearAdminGlobal());

        // Sin completar nada: en wizard solo se podría llegar a CARTERAS.
        // En edición, ESTADOS_CASO debe ser navegable directamente.
        Livewire::test(ConfiguradorProyecto::class, ['proyecto' => $modelo, 'modo' => 'edicion'])
            ->call('irAPaso', PasoConfiguracion::ESTADOS_CASO->value)
            ->assertSet('pasoActivo', PasoConfiguracion::ESTADOS_CASO);
    }

    public function test_en_modo_edicion_no_se_dispara_auto_avance_al_completar_paso(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->crearCarteraEn($proyecto); // CARTERAS quedará completo
        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $this->actingAs($this->crearAdminGlobal());

        Livewire::test(ConfiguradorProyecto::class, ['proyecto' => $modelo, 'modo' => 'edicion'])
            ->set('pasoActivo', PasoConfiguracion::CARTERAS)
            ->dispatch('configuracion-paso-completado')
            ->assertSet('pasoActivo', PasoConfiguracion::CARTERAS);
    }

    public function test_en_modo_edicion_pasoresumen_no_muestra_boton_finalizar(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->completarTodosLosObligatorios($proyecto);
        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $this->actingAs($this->crearAdminGlobal());

        Livewire::test(PasoResumen::class, ['proyecto' => $modelo, 'modo' => 'edicion'])
            ->assertSet('modo', 'edicion')
            ->assertDontSee('Marcar proyecto como configurado')
            ->assertDontSee('Volver al inicio del wizard');
    }

    public function test_en_modo_edicion_paso_inicial_es_datos_proyecto_si_no_hay_query(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $this->actingAs($this->crearAdminGlobal());

        Livewire::test(ConfiguradorProyecto::class, ['proyecto' => $modelo, 'modo' => 'edicion'])
            ->assertSet('pasoActivo', PasoConfiguracion::DATOS_PROYECTO);
    }

    public function test_en_modo_edicion_paso_inicial_respeta_query_string_valida(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $this->actingAs($this->crearAdminGlobal());

        Livewire::withQueryParams(['paso' => PasoConfiguracion::CARTERAS->value])
            ->test(ConfiguradorProyecto::class, ['proyecto' => $modelo, 'modo' => 'edicion'])
            ->assertSet('pasoActivo', PasoConfiguracion::CARTERAS);
    }

    public function test_en_modo_edicion_query_string_invalida_cae_a_datos_proyecto(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $this->actingAs($this->crearAdminGlobal());

        Livewire::withQueryParams(['paso' => 'paso_inexistente'])
            ->test(ConfiguradorProyecto::class, ['proyecto' => $modelo, 'modo' => 'edicion'])
            ->assertSet('pasoActivo', PasoConfiguracion::DATOS_PROYECTO);
    }

    public function test_sidebar_link_apunta_a_wizard_si_avance_incompleto(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $admin = $this->crearAdminGlobal();

        $resp = $this->actingAs($admin)
            ->get(route('proyectos.dashboard', ['proyecto_id' => $proyecto->id]));

        $resp->assertStatus(200);
        $resp->assertSee(route('admin.proyectos.configurar', ['proyecto' => $proyecto->public_id]), false);
        $resp->assertDontSee(route('admin.proyectos.configurar.editar', ['proyecto' => $proyecto->public_id]), false);
    }

    public function test_sidebar_link_apunta_a_edicion_si_avance_completo(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->completarTodosLosObligatorios($proyecto);
        $admin = $this->crearAdminGlobal();

        $resp = $this->actingAs($admin)
            ->get(route('proyectos.dashboard', ['proyecto_id' => $proyecto->id]));

        $resp->assertStatus(200);
        $resp->assertSee(route('admin.proyectos.configurar.editar', ['proyecto' => $proyecto->public_id]), false);
    }

    private function completarTodosLosObligatorios(stdClass $proyecto): void
    {
        $proyectoId = (int) $proyecto->id;
        $now = Carbon::now();

        $this->crearCarteraEn($proyecto);
        $this->crearEstadoCasoEn($proyecto);

        DB::table('tipos_gestion')->insert([
            'proyecto_id' => $proyectoId, 'codigo' => 'TG', 'nombre' => 'Tipo', 'orden' => 100,
            'activo' => true, 'creada_en' => $now, 'actualizada_en' => $now,
        ]);
        DB::table('resultados')->insert([
            'proyecto_id' => $proyectoId, 'codigo' => 'R', 'nombre' => 'Resultado', 'orden' => 100,
            'activo' => true, 'creada_en' => $now, 'actualizada_en' => $now,
        ]);
        DB::table('motivos_no_contacto')->insert([
            'proyecto_id' => $proyectoId, 'codigo' => 'M', 'nombre' => 'Motivo', 'orden' => 100,
            'activo' => true, 'creada_en' => $now, 'actualizada_en' => $now,
        ]);
        DB::table('tramos_mora')->insert([
            'proyecto_id' => $proyectoId, 'codigo' => 'TM', 'nombre' => 'Tramo', 'dias_desde' => 0,
            'orden' => 100, 'activo' => true, 'creada_en' => $now, 'actualizada_en' => $now,
        ]);
        DB::table('tipos_pago')->insert([
            'proyecto_id' => $proyectoId, 'codigo' => 'TP', 'nombre' => 'Tipo pago', 'orden' => 100,
            'activo' => true, 'creada_en' => $now, 'actualizada_en' => $now,
        ]);
    }
}
