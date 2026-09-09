<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Reportes;

use App\Modules\Reportes\Infrastructure\Http\Livewire\DashboardOperativo;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class ReportesOperativosTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_supervisor_accede_ruta_reportes_operativos(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $supervisor = $this->crearSupervisor($proyecto);

        $this->actingAs($supervisor)
            ->get(route('proyectos.reportes.operativos', ['proyecto_id' => $proyecto->id]))
            ->assertStatus(200);
    }

    public function test_gestor_recibe_403_en_ruta_reportes_operativos(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $gestor = $this->crearGestor($proyecto);

        $this->actingAs($gestor)
            ->get(route('proyectos.reportes.operativos', ['proyecto_id' => $proyecto->id]))
            ->assertStatus(403);
    }

    public function test_admin_global_accede_ruta_reportes(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $admin = $this->crearAdminGlobal();

        $this->actingAs($admin)
            ->get(route('proyectos.reportes.operativos', ['proyecto_id' => $proyecto->id]))
            ->assertStatus(200);
    }

    public function test_componente_dashboard_render_con_metricas_cero(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);

        $supervisor = $this->crearSupervisor($proyecto);
        $this->actingAs($supervisor);

        Livewire::test(DashboardOperativo::class)
            ->assertViewHas('cuentasIntentadas', 0)
            ->assertViewHas('cuentasGestionadas', 0)
            ->assertViewHas('totalGestiones', 0);
    }

    public function test_componente_aborta_403_si_usuario_sin_permiso(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);

        $gestor = $this->crearGestor($proyecto);
        $this->actingAs($gestor);

        Livewire::test(DashboardOperativo::class)->assertStatus(403);
    }
}
