<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Reportes;

use App\Modules\Reportes\Infrastructure\Http\Livewire\PanelDelDia;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * La portada del proyecto no pide permiso, y no debe pedirlo: todo el que
 * trabaja ahí tiene que poder entrar. Por eso el panel es quien decide qué
 * enseña a cada uno.
 *
 * El GESTOR no tiene ningún permiso `reportes.*` (comprobable en `rol_permiso`)
 * y aun así veía el ranking de productividad de sus compañeros y el dinero
 * comprometido del proyecto.
 */
final class PanelDelDiaVisibilidadTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_el_gestor_no_ve_el_ranking_de_sus_companeros_ni_el_dinero(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearGestor($proyecto));

        Livewire::test(PanelDelDia::class, ['proyectoId' => (int) $proyecto->id])
            ->assertSet('rango', 'hoy')
            ->assertViewHas('puedeVerSupervision', false)
            ->assertViewHas('dinero', null)
            ->assertDontSee(__('reportes.panel_por_usuario'))
            ->assertDontSee(__('reportes.panel_dinero'));
    }

    public function test_el_supervisor_si_los_ve(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearSupervisor($proyecto));

        Livewire::test(PanelDelDia::class, ['proyectoId' => (int) $proyecto->id])
            ->assertViewHas('puedeVerSupervision', true)
            ->assertSee(__('reportes.panel_por_usuario'))
            ->assertSee(__('reportes.panel_dinero'));
    }

    public function test_el_gestor_sigue_viendo_la_actividad_del_proyecto(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearGestor($proyecto));

        // Recortar no es vaciar: la portada tiene que seguir sirviendo para lo
        // que se entra, o el gestor se queda sin pantalla de inicio.
        Livewire::test(PanelDelDia::class, ['proyectoId' => (int) $proyecto->id])
            ->assertOk()
            ->assertSee(__('reportes.panel_efectividad'));
    }
}
