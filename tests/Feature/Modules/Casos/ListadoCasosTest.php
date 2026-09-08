<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Casos;

use App\Modules\Casos\Infrastructure\Http\Livewire\ListadoCasos;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * F34B — listado paginado de casos por proyecto + multi-tenancy.
 */
final class ListadoCasosTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_supervisor_ve_casos_del_proyecto(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto);
        $estado = $this->crearEstadoCasoEn($proyecto);

        foreach (range(1, 3) as $i) {
            $this->crearCasoEn($proyecto, ['cartera' => $cartera, 'estado' => $estado]);
        }

        $supervisor = $this->crearSupervisor($proyecto);
        $this->activarProyecto($proyecto);
        $this->actingAs($supervisor);

        $totalDb = (int) DB::table('casos')->where('proyecto_id', $proyecto->id)->count();
        $this->assertGreaterThan(0, $totalDb);

        $c = Livewire::test(ListadoCasos::class);
        $this->assertSame($totalDb, $c->viewData('totalProyecto'));
    }

    public function test_filtro_cartera(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $estado = $this->crearEstadoCasoEn($proyecto);
        $carteraA = $this->crearCarteraEn($proyecto);
        $carteraB = $this->crearCarteraEn($proyecto);

        foreach (range(1, 2) as $i) {
            $this->crearCasoEn($proyecto, ['cartera' => $carteraA, 'estado' => $estado]);
        }
        $this->crearCasoEn($proyecto, ['cartera' => $carteraB, 'estado' => $estado]);

        $supervisor = $this->crearSupervisor($proyecto);
        $this->activarProyecto($proyecto);
        $this->actingAs($supervisor);

        $carteraId = (int) $carteraA->id;
        $this->assertGreaterThan(0, $carteraId);

        $countCartera = (int) DB::table('casos')
            ->where('proyecto_id', $proyecto->id)
            ->where('cartera_id', $carteraId)
            ->count();
        $this->assertSame(2, $countCartera);

        $c = Livewire::test(ListadoCasos::class)
            ->set('carteraId', (string) $carteraId);
        $this->assertSame($countCartera, $c->viewData('casos')->total());
    }

    public function test_no_filtra_casos_de_otro_proyecto(): void
    {
        $proyectoA = $this->crearProyectoCobranza();
        $proyectoB = $this->crearProyectoCx();

        foreach (range(1, 2) as $i) {
            $this->crearCasoEn($proyectoA);
        }
        foreach (range(1, 2) as $i) {
            $this->crearCasoEn($proyectoB);
        }

        $supervisor = $this->crearSupervisor($proyectoA);
        $this->activarProyecto($proyectoA);
        $this->actingAs($supervisor);

        $totalA = (int) DB::table('casos')->where('proyecto_id', $proyectoA->id)->count();
        $this->assertGreaterThan(0, $totalA);
        $this->assertGreaterThan(0, (int) DB::table('casos')->where('proyecto_id', $proyectoB->id)->count());

        $c = Livewire::test(ListadoCasos::class);
        $this->assertSame($totalA, $c->viewData('totalProyecto'));

        $idsB = DB::table('casos')->where('proyecto_id', $proyectoB->id)->pluck('id')->all();
        foreach ($c->viewData('casos') as $caso) {
            $this->assertNotContains($caso->id, $idsB);
        }
    }

    public function test_gestor_accede_pantalla(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->crearCasoEn($proyecto);
        $gestor = $this->crearGestor($proyecto);

        $this->actingAs($gestor)
            ->get(route('proyectos.casos.lista', ['proyecto_id' => $proyecto->id]))
            ->assertStatus(200);
    }
}
