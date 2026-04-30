<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tenancy;

use App\Models\User;
use App\Modules\Tenancy\Infrastructure\Http\Livewire\AdminCarterasProyecto;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * F34B — CRUD de carteras vía Livewire dentro del proyecto activo.
 */
final class AdminCarterasProyectoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_supervisor_crea_cartera(): void
    {
        $proyectoId = $this->proyectoCobranza();
        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');
        $this->bindProyectoActivo($proyectoId);
        $this->actingAs($supervisor);

        Livewire::test(AdminCarterasProyecto::class)
            ->call('abrirFormCrear')
            ->set('form.codigo', 'NUEVA_CARTERA')
            ->set('form.nombre', 'Cartera nueva')
            ->set('form.descripcion', 'Descripción')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('formVisible', false);

        $this->assertDatabaseHas('carteras', [
            'proyecto_id' => $proyectoId,
            'codigo' => 'NUEVA_CARTERA',
            'nombre' => 'Cartera nueva',
            'activo' => true,
        ]);
    }

    public function test_codigo_duplicado_se_rechaza(): void
    {
        $proyectoId = $this->proyectoCobranza();
        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');
        $this->bindProyectoActivo($proyectoId);
        $this->actingAs($supervisor);

        // Crear primera cartera.
        Livewire::test(AdminCarterasProyecto::class)
            ->call('abrirFormCrear')
            ->set('form.codigo', 'CARTERA_X')
            ->set('form.nombre', 'X')
            ->call('guardar')
            ->assertHasNoErrors();

        // Segundo intento con mismo código → rechazo.
        Livewire::test(AdminCarterasProyecto::class)
            ->call('abrirFormCrear')
            ->set('form.codigo', 'CARTERA_X')
            ->set('form.nombre', 'X duplicado')
            ->call('guardar')
            ->assertHasErrors(['form.codigo']);
    }

    public function test_supervisor_edita_y_desactiva_cartera(): void
    {
        $proyectoId = $this->proyectoCobranza();
        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');
        $this->bindProyectoActivo($proyectoId);
        $this->actingAs($supervisor);

        $id = (int) DB::table('carteras')
            ->where('proyecto_id', $proyectoId)
            ->where('activo', true)
            ->value('id');
        $this->assertGreaterThan(0, $id);

        Livewire::test(AdminCarterasProyecto::class)
            ->call('abrirFormEditar', $id)
            ->set('form.nombre', 'Renombrada')
            ->call('guardar')
            ->assertHasNoErrors();
        $this->assertDatabaseHas('carteras', ['id' => $id, 'nombre' => 'Renombrada']);

        Livewire::test(AdminCarterasProyecto::class)->call('desactivar', $id);
        $this->assertFalse((bool) DB::table('carteras')->where('id', $id)->value('activo'));

        Livewire::test(AdminCarterasProyecto::class)->call('activar', $id);
        $this->assertTrue((bool) DB::table('carteras')->where('id', $id)->value('activo'));
    }

    public function test_carteras_aisladas_entre_proyectos(): void
    {
        $proyectoA = $this->proyectoCobranza();
        $proyectoB = $this->proyectoCx();

        // Cartera con mismo código en ambos proyectos: permitido.
        $supervisor = $this->crearConRol($proyectoA, 'SUPERVISOR');
        DB::table('usuario_proyecto_rol')->insert([
            'usuario_id' => $supervisor->id, 'proyecto_id' => $proyectoB,
            'rol_id' => DB::table('roles')->where('codigo', 'SUPERVISOR')->value('id'),
            'activo' => true,
        ]);
        $this->actingAs($supervisor);

        $this->bindProyectoActivo($proyectoA);
        Livewire::test(AdminCarterasProyecto::class)
            ->call('abrirFormCrear')
            ->set('form.codigo', 'CARTERA_COMUN')
            ->set('form.nombre', 'En A')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->bindProyectoActivo($proyectoB);
        Livewire::test(AdminCarterasProyecto::class)
            ->call('abrirFormCrear')
            ->set('form.codigo', 'CARTERA_COMUN')
            ->set('form.nombre', 'En B')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(2, (int) DB::table('carteras')->where('codigo', 'CARTERA_COMUN')->count());

        // Listado scoped: en A solo se ve la de A.
        $this->bindProyectoActivo($proyectoA);
        $c = Livewire::test(AdminCarterasProyecto::class);
        $carteras = $c->viewData('carteras');
        foreach ($carteras as $row) {
            // Verificar que ningún row leaked desde B.
            $this->assertNotSame('En B', (string) $row->nombre);
        }
    }

    public function test_auditor_no_accede_pantalla_carteras(): void
    {
        $proyectoId = $this->proyectoCobranza();
        $auditor = $this->crearConRol($proyectoId, 'AUDITOR');

        $this->actingAs($auditor)
            ->get(route('proyectos.carteras', ['proyecto_id' => $proyectoId]))
            ->assertStatus(403);
    }

    public function test_gestor_no_accede_pantalla_carteras(): void
    {
        $proyectoId = $this->proyectoCobranza();
        $gestor = $this->crearConRol($proyectoId, 'GESTOR');

        $this->actingAs($gestor)
            ->get(route('proyectos.carteras', ['proyecto_id' => $proyectoId]))
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
            'email' => strtolower($codigoRol).'.f34b.'.Str::random(6).'@crm.local',
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
