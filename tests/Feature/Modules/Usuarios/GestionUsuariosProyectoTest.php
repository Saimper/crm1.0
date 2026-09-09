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
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class GestionUsuariosProyectoTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_supervisor_accede_ruta_usuarios_del_proyecto(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $supervisor = $this->crearSupervisor($proyecto);

        $this->actingAs($supervisor)
            ->get(route('proyectos.usuarios', ['proyecto_id' => $proyecto->id]))
            ->assertStatus(200);
    }

    public function test_gestor_recibe_403_en_ruta_usuarios(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $gestor = $this->crearGestor($proyecto);

        $this->actingAs($gestor)
            ->get(route('proyectos.usuarios', ['proyecto_id' => $proyecto->id]))
            ->assertStatus(403);
    }

    public function test_buscar_usuario_por_email_encuentra(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->loginSupervisor($proyecto);

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
        $proyecto = $this->crearProyectoCobranza();
        $this->loginSupervisor($proyecto);

        Livewire::test(GestionUsuariosProyecto::class)
            ->call('abrirFormAsignar')
            ->set('buscarEmail', 'nadie@crm.local')
            ->call('buscarUsuario')
            ->assertHasErrors(['buscarEmail']);
    }

    public function test_buscar_admin_global_es_rechazado(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->loginSupervisor($proyecto);

        Livewire::test(GestionUsuariosProyecto::class)
            ->call('abrirFormAsignar')
            ->set('buscarEmail', 'admin@crm.local')       // ADMIN_GLOBAL del seeder
            ->call('buscarUsuario')
            ->assertHasErrors(['buscarEmail']);
    }

    public function test_asignar_rol_gestor_a_usuario_nuevo(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->loginSupervisor($proyecto);

        $target = User::query()->create([
            'name' => 'NuevoGestor', 'email' => 'nuevo@crm.local',
            'password' => Hash::make('x'), 'activo' => true,
        ]);
        $rolGestorId = (int) DB::table('roles')->where('codigo', 'GESTOR')->value('id');

        Livewire::test(GestionUsuariosProyecto::class)
            ->call('abrirFormAsignar')
            ->set('buscarEmail', 'nuevo@crm.local')
            ->call('buscarUsuario')
            ->set('rolAsignarValor', 'base:'.$rolGestorId)
            ->call('asignar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('usuario_proyecto_rol', [
            'usuario_id' => $target->id,
            'proyecto_id' => $proyecto->id,
            'rol_id' => $rolGestorId,
            'activo' => true,
        ]);
    }

    public function test_no_puede_asignar_rol_admin_global_via_este_componente(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->loginSupervisor($proyecto);

        $email = 'x.'.Str::random(4).'@crm.local';
        $target = User::query()->create([
            'name' => 'X', 'email' => $email,
            'password' => Hash::make('x'), 'activo' => true,
        ]);
        $rolAdminId = (int) DB::table('roles')->where('codigo', 'ADMIN_GLOBAL')->value('id');

        // `usuarioBuscadoId` es `#[Locked]`: el id lo fija el servidor al buscar
        // por correo, que es donde se filtra a los ADMIN_GLOBAL y a los de otro
        // mandante. El test entra por ese camino, como el navegador.
        Livewire::test(GestionUsuariosProyecto::class)
            ->set('buscarEmail', $email)
            ->call('buscarUsuario')
            ->set('rolAsignarValor', 'base:'.$rolAdminId)
            ->call('asignar')
            ->assertHasErrors(['rolAsignarValor']);

        $this->assertDatabaseMissing('usuario_proyecto_rol', [
            'usuario_id' => $target->id,
            'rol_id' => $rolAdminId,
        ]);
    }

    public function test_supervisor_no_puede_autorevocar_su_rol(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $supervisor = $this->crearSupervisor($proyecto);
        $this->activarProyecto($proyecto);
        $this->actingAs($supervisor);

        $rolSupervisorId = (int) DB::table('roles')->where('codigo', 'SUPERVISOR')->value('id');

        Livewire::test(GestionUsuariosProyecto::class)
            ->call('quitar', $supervisor->id, $rolSupervisorId);

        $this->assertDatabaseHas('usuario_proyecto_rol', [
            'usuario_id' => $supervisor->id,
            'proyecto_id' => $proyecto->id,
            'rol_id' => $rolSupervisorId,
        ]);
    }

    public function test_quitar_rol_a_otro_usuario(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $supervisor = $this->crearSupervisor($proyecto);
        $gestor = $this->crearGestor($proyecto);
        $rolGestorId = (int) DB::table('roles')->where('codigo', 'GESTOR')->value('id');

        $this->activarProyecto($proyecto);
        $this->actingAs($supervisor);

        Livewire::test(GestionUsuariosProyecto::class)
            ->call('quitar', $gestor->id, $rolGestorId);

        $this->assertDatabaseMissing('usuario_proyecto_rol', [
            'usuario_id' => $gestor->id,
            'proyecto_id' => $proyecto->id,
            'rol_id' => $rolGestorId,
        ]);
    }

    public function test_no_puede_quitar_rol_a_admin_global(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->loginSupervisor($proyecto);

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
            'usuario_id' => $admin->id, 'proyecto_id' => $proyecto->id, 'rol_id' => $rolGestorId, 'activo' => true,
        ]);

        Livewire::test(GestionUsuariosProyecto::class)
            ->call('quitar', $admin->id, $rolGestorId);

        $this->assertDatabaseHas('usuario_proyecto_rol', [
            'usuario_id' => $admin->id,
            'rol_id' => $rolGestorId,
        ]);
    }

    private function loginSupervisor(stdClass $proyecto): void
    {
        $supervisor = $this->crearSupervisor($proyecto);
        $this->activarProyecto($proyecto);
        $this->actingAs($supervisor);
    }
}
