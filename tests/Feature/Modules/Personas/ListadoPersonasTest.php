<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Personas;

use App\Models\User;
use App\Modules\Personas\Infrastructure\Http\Livewire\ListadoPersonas;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class ListadoPersonasTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_supervisor_ve_personas_del_proyecto(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->crearPersonaOperativaEn($proyecto, '1111111111');
        $this->crearPersonaOperativaEn($proyecto, '2222222222');

        $this->actuarComoSupervisor($proyecto);

        $c = Livewire::test(ListadoPersonas::class);
        $this->assertSame(2, $c->viewData('totalProyecto'));
    }

    public function test_filtro_busqueda_funciona(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->crearPersonaOperativaEn($proyecto, '7777777777');
        $this->crearPersonaOperativaEn($proyecto, '8888888888');

        $this->actuarComoSupervisor($proyecto);

        $c = Livewire::test(ListadoPersonas::class)->set('busqueda', '7777777777');
        $personas = $c->viewData('personas');
        $this->assertSame(1, $personas->total());
    }

    public function test_no_filtra_personas_de_otro_proyecto(): void
    {
        $proyectoA = $this->crearProyectoCobranza();
        $proyectoB = $this->crearProyectoCx();
        $this->crearPersonaOperativaEn($proyectoA, '1010101010');
        $this->crearPersonaOperativaEn($proyectoB, '2020202020');

        $this->actuarComoSupervisor($proyectoA);

        $c = Livewire::test(ListadoPersonas::class);
        $this->assertSame(1, $c->viewData('totalProyecto'));

        $personas = $c->viewData('personas');
        $idsB = DB::table('personas')->where('proyecto_id', $proyectoB->id)->pluck('id')->all();
        foreach ($personas as $p) {
            $this->assertNotContains($p->id, $idsB);
        }
    }

    /**
     * Un rol acotado por cartera (F22) ve la lista recortada, y también los dos
     * números que la acompañan: el subtítulo del padrón y la columna «casos».
     * Si el subtítulo dijera 3 y debajo se viera 1, estaría contando lo que ese
     * usuario no puede mirar; y la columna delataría cuántos casos más tiene
     * esa persona en carteras que no puede abrir.
     */
    public function test_un_rol_acotado_por_cartera_recorta_la_lista_y_sus_numeros(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $permitida = $this->crearCarteraEn($proyecto);
        $vetada = $this->crearCarteraEn($proyecto);
        $estado = $this->crearEstadoCasoEn($proyecto);

        $dentro = $this->crearPersonaEn($proyecto, '7600000001');
        $fuera = $this->crearPersonaEn($proyecto, '7600000002');
        $this->crearPersonaEn($proyecto, '7600000003');

        $this->crearCasoEn($proyecto, ['cartera' => $permitida, 'estado' => $estado, 'persona' => $dentro]);
        $this->crearCasoEn($proyecto, ['cartera' => $vetada, 'estado' => $estado, 'persona' => $dentro]);
        $this->crearCasoEn($proyecto, ['cartera' => $vetada, 'estado' => $estado, 'persona' => $fuera]);

        $supervisor = $this->crearSupervisor($proyecto);
        DB::table('usuario_proyecto_rol_cartera')->insert([
            'usuario_id' => $supervisor->id,
            'proyecto_id' => $proyecto->id,
            'rol_id' => (int) DB::table('roles')->where('codigo', 'SUPERVISOR')->value('id'),
            'cartera_id' => $permitida->id,
        ]);

        $this->activarProyecto($proyecto);
        $this->actingAs($supervisor);

        $c = Livewire::test(ListadoPersonas::class);
        $personas = iterator_to_array($c->viewData('personas'));

        $this->assertCount(1, $personas);
        $this->assertSame('7600000001', (string) $personas[0]->identificacion);
        $this->assertSame(1, (int) $personas[0]->total_casos, 'El caso de la cartera vetada no se cuenta.');
        $this->assertSame(1, $c->viewData('totalProyecto'));
    }

    public function test_gestor_accede_pantalla(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $gestor = $this->crearGestor($proyecto);

        $this->actingAs($gestor)
            ->get(route('proyectos.personas.lista', ['proyecto_id' => $proyecto->id]))
            ->assertStatus(200);
    }

    public function test_sin_rol_recibe_403(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $u = User::query()->create([
            'name' => 'Sin',
            'email' => 'sin.b1.'.Str::random(4).'@crm.local',
            'password' => Hash::make('x'),
            'activo' => true,
        ]);

        $this->actingAs($u)
            ->get(route('proyectos.personas.lista', ['proyecto_id' => $proyecto->id]))
            ->assertStatus(403);
    }

    private function actuarComoSupervisor(stdClass $proyecto): void
    {
        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearSupervisor($proyecto));
    }

    private function crearPersonaOperativaEn(stdClass $proyecto, ?string $identificacion = null): stdClass
    {
        $persona = $this->crearPersonaEn($proyecto, $identificacion);
        $this->crearCasoEn($proyecto, ['persona' => $persona]);

        return $persona;
    }
}
