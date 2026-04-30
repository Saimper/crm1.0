<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Casos;

use App\Models\User;
use App\Modules\Casos\Infrastructure\Http\Livewire\ListadoCasos;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * F34B — listado paginado de casos por proyecto + multi-tenancy.
 */
final class ListadoCasosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_supervisor_ve_casos_del_proyecto(): void
    {
        $proyectoId = $this->proyectoCobranza();
        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');
        $this->bindProyectoActivo($proyectoId);
        $this->actingAs($supervisor);

        $totalDb = (int) DB::table('casos')->where('proyecto_id', $proyectoId)->count();
        $this->assertGreaterThan(0, $totalDb);

        $c = Livewire::test(ListadoCasos::class);
        $this->assertSame($totalDb, $c->viewData('totalProyecto'));
    }

    public function test_filtro_cartera(): void
    {
        $proyectoId = $this->proyectoCobranza();
        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');
        $this->bindProyectoActivo($proyectoId);
        $this->actingAs($supervisor);

        $carteraId = (int) DB::table('carteras')
            ->where('proyecto_id', $proyectoId)
            ->value('id');
        $this->assertGreaterThan(0, $carteraId);

        $countCartera = (int) DB::table('casos')
            ->where('proyecto_id', $proyectoId)
            ->where('cartera_id', $carteraId)
            ->count();

        $c = Livewire::test(ListadoCasos::class)
            ->set('carteraId', (string) $carteraId);
        $this->assertSame($countCartera, $c->viewData('casos')->total());
    }

    public function test_no_filtra_casos_de_otro_proyecto(): void
    {
        $proyectoA = $this->proyectoCobranza();
        $proyectoB = $this->proyectoCx();

        $supervisor = $this->crearConRol($proyectoA, 'SUPERVISOR');
        $this->bindProyectoActivo($proyectoA);
        $this->actingAs($supervisor);

        $totalA = (int) DB::table('casos')->where('proyecto_id', $proyectoA)->count();
        $this->assertGreaterThan(0, $totalA);
        $this->assertGreaterThan(0, (int) DB::table('casos')->where('proyecto_id', $proyectoB)->count());

        $c = Livewire::test(ListadoCasos::class);
        $this->assertSame($totalA, $c->viewData('totalProyecto'));

        $idsB = DB::table('casos')->where('proyecto_id', $proyectoB)->pluck('id')->all();
        foreach ($c->viewData('casos') as $caso) {
            $this->assertNotContains($caso->id, $idsB);
        }
    }

    public function test_gestor_accede_pantalla(): void
    {
        $proyectoId = $this->proyectoCobranza();
        $gestor = $this->crearConRol($proyectoId, 'GESTOR');

        $this->actingAs($gestor)
            ->get(route('proyectos.casos.lista', ['proyecto_id' => $proyectoId]))
            ->assertStatus(200);
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
            'email' => strtolower($codigoRol).'.lc.'.Str::random(6).'@crm.local',
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
