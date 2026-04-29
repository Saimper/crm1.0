<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Usuarios;

use App\Models\User;
use App\Modules\Usuarios\Infrastructure\Http\Livewire\GestionUsuariosProyecto;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

final class GestionUsuariosProyectoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_supervisor_accede_ruta_usuarios_del_proyecto(): void
    {
        $proyectoId = $this->proyectoId();
        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');

        $this->actingAs($supervisor)
            ->get(route('proyectos.usuarios', ['proyecto_id' => $proyectoId]))
            ->assertStatus(200);
    }

    public function test_gestor_recibe_403_en_ruta_usuarios(): void
    {
        $proyectoId = $this->proyectoId();
        $gestor = $this->crearConRol($proyectoId, 'GESTOR');

        $this->actingAs($gestor)
            ->get(route('proyectos.usuarios', ['proyecto_id' => $proyectoId]))
            ->assertStatus(403);
    }

    public function test_buscar_usuario_por_email_encuentra(): void
    {
        $proyectoId = $this->proyectoId();
        $this->loginSupervisor($proyectoId);

        $target = User::query()->create([
            'name' => 'Target', 'email' => 'target@crm.local',
            'password' => Hash::make('x'), 'activo' => true,
        ]);

        Livewire::test(GestionUsuariosProyecto::class)
            ->call('abrirFormAsignar')
            ->set('buscarEmail', 'target@crm.local')
            ->call('buscarUsuario')
            ->assertHasNoErrors()
            ->assertSet('usuarioBuscadoId', $target->id);
    }

    public function test_buscar_email_inexistente_muestra_error(): void
    {
        $proyectoId = $this->proyectoId();
        $this->loginSupervisor($proyectoId);

        Livewire::test(GestionUsuariosProyecto::class)
            ->call('abrirFormAsignar')
            ->set('buscarEmail', 'nadie@crm.local')
            ->call('buscarUsuario')
            ->assertHasErrors(['buscarEmail']);
    }

    public function test_buscar_admin_global_es_rechazado(): void
    {
        $proyectoId = $this->proyectoId();
        $this->loginSupervisor($proyectoId);

        Livewire::test(GestionUsuariosProyecto::class)
            ->call('abrirFormAsignar')
            ->set('buscarEmail', 'admin@crm.local')       // ADMIN_GLOBAL del seeder
            ->call('buscarUsuario')
            ->assertHasErrors(['buscarEmail']);
    }

    public function test_asignar_rol_gestor_a_usuario_nuevo(): void
    {
        $proyectoId = $this->proyectoId();
        $this->loginSupervisor($proyectoId);

        $target = User::query()->create([
            'name' => 'NuevoGestor', 'email' => 'nuevo@crm.local',
            'password' => Hash::make('x'), 'activo' => true,
        ]);
        $rolGestorId = (int) DB::table('roles')->where('codigo', 'GESTOR')->value('id');

        Livewire::test(GestionUsuariosProyecto::class)
            ->call('abrirFormAsignar')
            ->set('buscarEmail', 'nuevo@crm.local')
            ->call('buscarUsuario')
            ->set('rolAsignarId', $rolGestorId)
            ->call('asignar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('usuario_proyecto_rol', [
            'usuario_id'  => $target->id,
            'proyecto_id' => $proyectoId,
            'rol_id'      => $rolGestorId,
            'activo'      => true,
        ]);
    }

    public function test_no_puede_asignar_rol_admin_global_via_este_componente(): void
    {
        $proyectoId = $this->proyectoId();
        $this->loginSupervisor($proyectoId);

        $target = User::query()->create([
            'name' => 'X', 'email' => 'x.'.Str::random(4).'@crm.local',
            'password' => Hash::make('x'), 'activo' => true,
        ]);
        $rolAdminId = (int) DB::table('roles')->where('codigo', 'ADMIN_GLOBAL')->value('id');

        Livewire::test(GestionUsuariosProyecto::class)
            ->set('usuarioBuscadoId', $target->id)
            ->set('rolAsignarId', $rolAdminId)
            ->call('asignar')
            ->assertHasErrors(['rolAsignarId']);

        $this->assertDatabaseMissing('usuario_proyecto_rol', [
            'usuario_id' => $target->id,
            'rol_id'     => $rolAdminId,
        ]);
    }

    public function test_supervisor_no_puede_autorevocar_su_rol(): void
    {
        $proyectoId = $this->proyectoId();
        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');
        $this->app->instance('tenancy.proyecto_activo', DB::table('proyectos')->find($proyectoId));
        $this->actingAs($supervisor);

        $rolSupervisorId = (int) DB::table('roles')->where('codigo', 'SUPERVISOR')->value('id');

        Livewire::test(GestionUsuariosProyecto::class)
            ->call('quitar', $supervisor->id, $rolSupervisorId);

        $this->assertDatabaseHas('usuario_proyecto_rol', [
            'usuario_id'  => $supervisor->id,
            'proyecto_id' => $proyectoId,
            'rol_id'      => $rolSupervisorId,
        ]);
    }

    public function test_quitar_rol_a_otro_usuario(): void
    {
        $proyectoId = $this->proyectoId();
        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');
        $gestor     = $this->crearConRol($proyectoId, 'GESTOR');
        $rolGestorId = (int) DB::table('roles')->where('codigo', 'GESTOR')->value('id');

        $this->app->instance('tenancy.proyecto_activo', DB::table('proyectos')->find($proyectoId));
        $this->actingAs($supervisor);

        Livewire::test(GestionUsuariosProyecto::class)
            ->call('quitar', $gestor->id, $rolGestorId);

        $this->assertDatabaseMissing('usuario_proyecto_rol', [
            'usuario_id' => $gestor->id,
            'proyecto_id' => $proyectoId,
            'rol_id' => $rolGestorId,
        ]);
    }

    public function test_no_puede_quitar_rol_a_admin_global(): void
    {
        $proyectoId = $this->proyectoId();
        $this->loginSupervisor($proyectoId);

        // Crear un admin global con asignación artificial al proyecto (no debería aparecer en lista,
        // pero si alguien llama `quitar` con su id, debe ser rechazado).
        $admin = User::query()->create([
            'name' => 'OtroAdmin', 'email' => 'otro.admin@crm.local',
            'password' => Hash::make('x'), 'activo' => true,
        ]);
        $rolAdminGlobalId = (int) DB::table('roles')->where('codigo', 'ADMIN_GLOBAL')->value('id');
        DB::table('usuario_global_rol')->insert([
            'usuario_id' => $admin->id, 'rol_id' => $rolAdminGlobalId,
        ]);
        $rolGestorId = (int) DB::table('roles')->where('codigo', 'GESTOR')->value('id');
        DB::table('usuario_proyecto_rol')->insert([
            'usuario_id' => $admin->id, 'proyecto_id' => $proyectoId, 'rol_id' => $rolGestorId, 'activo' => true,
        ]);

        Livewire::test(GestionUsuariosProyecto::class)
            ->call('quitar', $admin->id, $rolGestorId);

        $this->assertDatabaseHas('usuario_proyecto_rol', [
            'usuario_id' => $admin->id,
            'rol_id' => $rolGestorId,
        ]);
    }

    private function proyectoId(): int
    {
        return (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
    }

    private function loginSupervisor(int $proyectoId): void
    {
        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');
        $this->app->instance('tenancy.proyecto_activo', DB::table('proyectos')->find($proyectoId));
        $this->actingAs($supervisor);
    }

    private function crearConRol(int $proyectoId, string $codigoRol): User
    {
        /** @var User $u */
        $u = User::query()->create([
            'name'     => ucfirst(strtolower($codigoRol)),
            'email'    => strtolower($codigoRol).'.'.Str::random(6).'@crm.local',
            'password' => Hash::make('x'),
            'activo'   => true,
        ]);
        $rolId = (int) DB::table('roles')->where('codigo', $codigoRol)->value('id');
        DB::table('usuario_proyecto_rol')->insert([
            'usuario_id' => $u->id, 'proyecto_id' => $proyectoId, 'rol_id' => $rolId, 'activo' => true,
        ]);

        return $u;
    }
}
