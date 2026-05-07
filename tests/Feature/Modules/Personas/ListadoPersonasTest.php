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
        $this->crearPersonaEn($proyecto, '1111111111');
        $this->crearPersonaEn($proyecto, '2222222222');

        $this->actuarComoSupervisor($proyecto);

        $c = Livewire::test(ListadoPersonas::class);
        $this->assertSame(2, $c->viewData('totalProyecto'));
    }

    public function test_filtro_busqueda_funciona(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->crearPersonaEn($proyecto, '7777777777');
        $this->crearPersonaEn($proyecto, '8888888888');

        $this->actuarComoSupervisor($proyecto);

        $c = Livewire::test(ListadoPersonas::class)->set('busqueda', '7777777777');
        $personas = $c->viewData('personas');
        $this->assertSame(1, $personas->total());
    }

    public function test_no_filtra_personas_de_otro_proyecto(): void
    {
        $proyectoA = $this->crearProyectoCobranza();
        $proyectoB = $this->crearProyectoCx();
        $this->crearPersonaEn($proyectoA, '1010101010');
        $this->crearPersonaEn($proyectoB, '2020202020');

        $this->actuarComoSupervisor($proyectoA);

        $c = Livewire::test(ListadoPersonas::class);
        $this->assertSame(1, $c->viewData('totalProyecto'));

        $personas = $c->viewData('personas');
        $idsB = DB::table('personas')->where('proyecto_id', $proyectoB->id)->pluck('id')->all();
        foreach ($personas as $p) {
            $this->assertNotContains($p->id, $idsB);
        }
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
}
