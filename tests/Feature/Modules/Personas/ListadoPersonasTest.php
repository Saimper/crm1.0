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
use Tests\TestCase;

/**
 * F34B — listado paginado de personas por proyecto + multi-tenancy.
 */
final class ListadoPersonasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_supervisor_ve_personas_del_proyecto(): void
    {
        $proyectoId = $this->proyectoCobranza();
        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');
        $this->bindProyectoActivo($proyectoId);
        $this->actingAs($supervisor);

        $totalDb = (int) DB::table('personas')->where('proyecto_id', $proyectoId)->count();
        $this->assertGreaterThan(0, $totalDb);

        $c = Livewire::test(ListadoPersonas::class);
        $this->assertSame($totalDb, $c->viewData('totalProyecto'));
    }

    public function test_filtro_busqueda_funciona(): void
    {
        $proyectoId = $this->proyectoCobranza();
        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');
        $this->bindProyectoActivo($proyectoId);
        $this->actingAs($supervisor);

        $unaPersona = (object) DB::table('personas')
            ->where('proyecto_id', $proyectoId)
            ->whereNotNull('identificacion')
            ->first();
        $this->assertNotNull($unaPersona);

        $c = Livewire::test(ListadoPersonas::class)
            ->set('busqueda', $unaPersona->identificacion);
        $personas = $c->viewData('personas');
        $this->assertGreaterThanOrEqual(1, $personas->total());
    }

    public function test_no_filtra_personas_de_otro_proyecto(): void
    {
        $proyectoA = $this->proyectoCobranza();
        $proyectoB = $this->proyectoCx();

        $supervisor = $this->crearConRol($proyectoA, 'SUPERVISOR');
        $this->bindProyectoActivo($proyectoA);
        $this->actingAs($supervisor);

        $totalA = (int) DB::table('personas')->where('proyecto_id', $proyectoA)->count();
        $totalB = (int) DB::table('personas')->where('proyecto_id', $proyectoB)->count();
        $this->assertGreaterThan(0, $totalA);
        $this->assertGreaterThan(0, $totalB);

        $c = Livewire::test(ListadoPersonas::class);
        $this->assertSame($totalA, $c->viewData('totalProyecto'));

        $personasA = $c->viewData('personas');
        $idsB = DB::table('personas')->where('proyecto_id', $proyectoB)->pluck('id')->all();
        foreach ($personasA as $p) {
            $this->assertNotContains($p->id, $idsB);
        }
    }

    public function test_gestor_accede_pantalla(): void
    {
        $proyectoId = $this->proyectoCobranza();
        $gestor = $this->crearConRol($proyectoId, 'GESTOR');

        $this->actingAs($gestor)
            ->get(route('proyectos.personas.lista', ['proyecto_id' => $proyectoId]))
            ->assertStatus(200);
    }

    public function test_sin_rol_recibe_403(): void
    {
        $proyectoId = $this->proyectoCobranza();
        $u = User::query()->create([
            'name' => 'Sin', 'email' => 'sin.f34b.'.Str::random(4).'@crm.local',
            'password' => Hash::make('x'), 'activo' => true,
        ]);

        $this->actingAs($u)
            ->get(route('proyectos.personas.lista', ['proyecto_id' => $proyectoId]))
            ->assertStatus(403);
    }

    private function proyectoCobranza(): int
    {
        return (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
    }

    private function proyectoCx(): int
    {
        return (int) DB::table('proyectos')->where('codigo', 'SOPORTE_DEMO_2026')->value('id');
    }

    private function bindProyectoActivo(int $proyectoId): void
    {
        $this->app->instance('tenancy.proyecto_activo', DB::table('proyectos')->find($proyectoId));
    }

    private function crearConRol(int $proyectoId, string $codigoRol): User
    {
        /** @var User $u */
        $u = User::query()->create([
            'name' => ucfirst(strtolower($codigoRol)),
            'email' => strtolower($codigoRol).'.lp.'.Str::random(6).'@crm.local',
            'password' => Hash::make('x'),
            'activo' => true,
        ]);
        $rolId = (int) DB::table('roles')->where('codigo', $codigoRol)->value('id');
        DB::table('usuario_proyecto_rol')->insert([
            'usuario_id' => $u->id, 'proyecto_id' => $proyectoId,
            'rol_id' => $rolId, 'activo' => true,
        ]);

        return $u;
    }
}
