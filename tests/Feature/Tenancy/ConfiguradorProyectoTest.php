<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Modules\Tenancy\Domain\ConfiguracionProyecto\PasoConfiguracion;
use App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorProyecto;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\ProyectoModel;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class ConfiguradorProyectoTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_global_puede_acceder_al_wizard(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $admin = $this->crearAdminGlobal();

        $this->actingAs($admin)
            ->get(route('admin.proyectos.configurar', ['proyecto' => $proyecto->public_id]))
            ->assertStatus(200)
            ->assertSee('Configurar proyecto');
    }

    public function test_supervisor_no_puede_acceder(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $supervisor = $this->crearSupervisor($proyecto);

        $this->actingAs($supervisor)
            ->get(route('admin.proyectos.configurar', ['proyecto' => $proyecto->public_id]))
            ->assertStatus(403);
    }

    public function test_gestor_no_puede_acceder(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $gestor = $this->crearGestor($proyecto);

        $this->actingAs($gestor)
            ->get(route('admin.proyectos.configurar', ['proyecto' => $proyecto->public_id]))
            ->assertStatus(403);
    }

    public function test_proyecto_otro_tenant_devuelve_404(): void
    {
        $admin = $this->crearAdminGlobal();
        $publicIdInexistente = '01HXNONEXISTENT0000000000A';

        $this->actingAs($admin)
            ->get(route('admin.proyectos.configurar', ['proyecto' => $publicIdInexistente]))
            ->assertStatus(404);
    }

    public function test_avance_inicial_de_proyecto_recien_creado_muestra_paso_carteras(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $admin = $this->crearAdminGlobal();
        $this->actingAs($admin);

        Livewire::test(ConfiguradorProyecto::class, ['proyecto' => $modelo])
            ->assertSet('pasoActivo', PasoConfiguracion::CARTERAS);
    }

    public function test_no_se_puede_saltar_a_paso_si_anteriores_incompletos(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $admin = $this->crearAdminGlobal();
        $this->actingAs($admin);

        Livewire::test(ConfiguradorProyecto::class, ['proyecto' => $modelo])
            ->call('irAPaso', PasoConfiguracion::TIPOS_GESTION->value)
            ->assertSet('pasoActivo', PasoConfiguracion::CARTERAS);
    }

    public function test_en_modo_wizard_auto_avance_sigue_funcionando(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->crearCarteraEn($proyecto); // CARTERAS quedará completo
        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $admin = $this->crearAdminGlobal();
        $this->actingAs($admin);

        // mount calcula pasoActual = ESTADOS_CASO (porque CARTERAS ya está hecho).
        // Para forzar el escenario "estoy en CARTERAS y completo el paso",
        // navego allí explícitamente y luego disparo el evento.
        Livewire::test(ConfiguradorProyecto::class, ['proyecto' => $modelo])
            ->set('pasoActivo', PasoConfiguracion::CARTERAS)
            ->dispatch('configuracion-paso-completado')
            ->assertSet('pasoActivo', PasoConfiguracion::ESTADOS_CASO);
    }
}
