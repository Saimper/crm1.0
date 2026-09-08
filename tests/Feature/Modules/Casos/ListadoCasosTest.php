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

    /**
     * El listado enseña sólo las carteras del rol (F22): la pantalla y la
     * descarga tienen que decir lo mismo, o el supervisor acotado ve una
     * cartera en el CSV que no encuentra en su bandeja.
     */
    public function test_un_rol_acotado_por_cartera_solo_ve_esas_carteras(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $estado = $this->crearEstadoCasoEn($proyecto);
        $permitida = $this->crearCarteraEn($proyecto);
        $vetada = $this->crearCarteraEn($proyecto);

        $this->crearCasoEn($proyecto, ['cartera' => $permitida, 'estado' => $estado]);
        $this->crearCasoEn($proyecto, ['cartera' => $vetada, 'estado' => $estado]);

        $supervisor = $this->crearSupervisor($proyecto);
        DB::table('usuario_proyecto_rol_cartera')->insert([
            'usuario_id' => $supervisor->id,
            'proyecto_id' => $proyecto->id,
            'rol_id' => (int) DB::table('roles')->where('codigo', 'SUPERVISOR')->value('id'),
            'cartera_id' => $permitida->id,
        ]);

        $this->activarProyecto($proyecto);
        $this->actingAs($supervisor);

        $casos = iterator_to_array(Livewire::test(ListadoCasos::class)->viewData('casos'));

        $this->assertCount(1, $casos);
        $this->assertSame($permitida->nombre, $casos[0]->col_cartera);
    }

    /**
     * Las cabeceras ordenan, y la clave que llega del cliente se contrasta
     * contra el catálogo de columnas: nunca entra tal cual en el ORDER BY.
     */
    public function test_las_cabeceras_ordenan_y_el_segundo_clic_da_la_vuelta(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto);
        $estado = $this->crearEstadoCasoEn($proyecto);

        foreach (['2200000003', '2200000001', '2200000002'] as $identificacion) {
            $this->crearCasoEn($proyecto, [
                'cartera' => $cartera,
                'estado' => $estado,
                'persona' => $this->crearPersonaEn($proyecto, $identificacion),
            ]);
        }

        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearSupervisor($proyecto));

        $c = Livewire::test(ListadoCasos::class)->call('ordenarPor', 'identificacion');

        $this->assertSame('identificacion', $c->get('orden'));
        $this->assertSame('asc', $c->get('direccion'));
        $this->assertSame(
            ['2200000001', '2200000002', '2200000003'],
            array_map(static fn (object $f): string => (string) $f->col_identificacion, iterator_to_array($c->viewData('casos'))),
        );

        $c->call('ordenarPor', 'identificacion');

        $this->assertSame('desc', $c->get('direccion'));
        $this->assertSame(
            ['2200000003', '2200000002', '2200000001'],
            array_map(static fn (object $f): string => (string) $f->col_identificacion, iterator_to_array($c->viewData('casos'))),
        );
    }

    public function test_una_clave_de_orden_inventada_no_llega_a_la_consulta(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->crearCasoEn($proyecto, ['cartera' => $this->crearCarteraEn($proyecto), 'estado' => $this->crearEstadoCasoEn($proyecto)]);

        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearSupervisor($proyecto));

        // Ni por el método ni por la URL, que es la puerta que nadie filtra.
        $c = Livewire::test(ListadoCasos::class)
            ->call('ordenarPor', '(select 1)')
            ->assertOk();
        $this->assertSame('', $c->get('orden'), 'La clave no está en el catálogo: no se guarda.');

        Livewire::withUrlParams(['orden' => 'casos.id; drop table casos', 'dir' => 'desc'])
            ->test(ListadoCasos::class)
            ->assertOk();

        $this->assertSame(1, DB::table('casos')->where('proyecto_id', $proyecto->id)->count());
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
