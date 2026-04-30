<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Usuarios;

use App\Models\User;
use App\Modules\Usuarios\Infrastructure\Http\Livewire\AdminEquiposProyecto;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

final class AdminEquiposProyectoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_supervisor_accede_ruta_equipos(): void
    {
        $proyectoId = $this->proyectoId();
        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');

        $this->actingAs($supervisor)
            ->get(route('proyectos.equipos', ['proyecto_id' => $proyectoId]))
            ->assertStatus(200);
    }

    public function test_gestor_recibe_403_en_ruta_equipos(): void
    {
        $proyectoId = $this->proyectoId();
        $gestor = $this->crearConRol($proyectoId, 'GESTOR');

        $this->actingAs($gestor)
            ->get(route('proyectos.equipos', ['proyecto_id' => $proyectoId]))
            ->assertStatus(403);
    }

    public function test_supervisor_crea_equipo(): void
    {
        $proyectoId = $this->proyectoId();
        $this->bindProyectoActivo($proyectoId);
        $this->actingAs($this->crearConRol($proyectoId, 'SUPERVISOR'));

        Livewire::test(AdminEquiposProyecto::class)
            ->call('abrirFormCrear')
            ->set('formCodigo', 'EQ_TEST')
            ->set('formNombre', 'Equipo de prueba')
            ->call('guardarEquipo')
            ->assertHasNoErrors()
            ->assertSet('formEquipoVisible', false);

        $this->assertDatabaseHas('equipos', [
            'proyecto_id' => $proyectoId,
            'codigo' => 'EQ_TEST',
            'nombre' => 'Equipo de prueba',
            'activo' => true,
        ]);
    }

    public function test_codigo_duplicado_en_mismo_proyecto_es_rechazado(): void
    {
        $proyectoId = $this->proyectoId();
        $this->bindProyectoActivo($proyectoId);
        $this->actingAs($this->crearConRol($proyectoId, 'SUPERVISOR'));

        $c = Livewire::test(AdminEquiposProyecto::class);
        $c->call('abrirFormCrear')
            ->set('formCodigo', 'DUP')
            ->set('formNombre', 'Primero')
            ->call('guardarEquipo')
            ->assertHasNoErrors();

        $c->call('abrirFormCrear')
            ->set('formCodigo', 'DUP')
            ->set('formNombre', 'Segundo')
            ->call('guardarEquipo')
            ->assertHasErrors(['formCodigo']);
    }

    public function test_agregar_y_quitar_miembro(): void
    {
        $proyectoId = $this->proyectoId();
        $this->bindProyectoActivo($proyectoId);
        $this->actingAs($this->crearConRol($proyectoId, 'SUPERVISOR'));

        $gestor = $this->crearConRol($proyectoId, 'GESTOR');

        $c = Livewire::test(AdminEquiposProyecto::class)
            ->call('abrirFormCrear')
            ->set('formCodigo', 'EQ_MIEMBROS')
            ->set('formNombre', 'Con miembros')
            ->call('guardarEquipo');

        $equipoId = (int) DB::table('equipos')
            ->where('proyecto_id', $proyectoId)->where('codigo', 'EQ_MIEMBROS')->value('id');

        $c->call('gestionarMiembros', $equipoId)
            ->set('buscarEmail', $gestor->email)
            ->call('buscarUsuario')
            ->assertHasNoErrors()
            ->call('agregarMiembro');

        $this->assertDatabaseHas('equipo_usuario', [
            'equipo_id' => $equipoId,
            'usuario_id' => $gestor->id,
            'proyecto_id' => $proyectoId,
        ]);

        $c->call('quitarMiembro', $gestor->id);

        $this->assertDatabaseMissing('equipo_usuario', [
            'equipo_id' => $equipoId,
            'usuario_id' => $gestor->id,
        ]);
    }

    public function test_no_puede_agregar_admin_global_como_miembro(): void
    {
        $proyectoId = $this->proyectoId();
        $this->bindProyectoActivo($proyectoId);
        $this->actingAs($this->crearConRol($proyectoId, 'SUPERVISOR'));

        $admin = User::query()->create([
            'name' => 'Admin', 'email' => 'admin.equipo.'.Str::random(3).'@crm.local',
            'password' => Hash::make('x'), 'activo' => true,
        ]);
        $rolAdminId = (int) DB::table('roles')->where('codigo', 'ADMIN_GLOBAL')->value('id');
        DB::table('usuario_global_rol')->insert([
            'usuario_id' => $admin->id, 'rol_id' => $rolAdminId,
        ]);

        Livewire::test(AdminEquiposProyecto::class)
            ->call('abrirFormCrear')
            ->set('formCodigo', 'EQ_ADMIN')
            ->set('formNombre', 'Test admin')
            ->call('guardarEquipo');

        $equipoId = (int) DB::table('equipos')
            ->where('proyecto_id', $proyectoId)->where('codigo', 'EQ_ADMIN')->value('id');

        Livewire::test(AdminEquiposProyecto::class)
            ->call('gestionarMiembros', $equipoId)
            ->set('buscarEmail', $admin->email)
            ->call('buscarUsuario')
            ->assertHasErrors(['buscarEmail']);
    }

    public function test_no_puede_agregar_usuario_sin_rol_en_el_proyecto(): void
    {
        $proyectoId = $this->proyectoId();
        $this->bindProyectoActivo($proyectoId);
        $this->actingAs($this->crearConRol($proyectoId, 'SUPERVISOR'));

        // Usuario sin rol en este proyecto
        $extra = User::query()->create([
            'name' => 'Suelto', 'email' => 'suelto.'.Str::random(3).'@crm.local',
            'password' => Hash::make('x'), 'activo' => true,
        ]);

        Livewire::test(AdminEquiposProyecto::class)
            ->call('abrirFormCrear')
            ->set('formCodigo', 'EQ_NULL')
            ->set('formNombre', 'Test sin-rol')
            ->call('guardarEquipo');

        $equipoId = (int) DB::table('equipos')
            ->where('proyecto_id', $proyectoId)->where('codigo', 'EQ_NULL')->value('id');

        Livewire::test(AdminEquiposProyecto::class)
            ->call('gestionarMiembros', $equipoId)
            ->set('buscarEmail', $extra->email)
            ->call('buscarUsuario')
            ->assertHasErrors(['buscarEmail']);
    }

    public function test_desactivar_y_activar_equipo(): void
    {
        $proyectoId = $this->proyectoId();
        $this->bindProyectoActivo($proyectoId);
        $this->actingAs($this->crearConRol($proyectoId, 'SUPERVISOR'));

        Livewire::test(AdminEquiposProyecto::class)
            ->call('abrirFormCrear')
            ->set('formCodigo', 'EQ_TOGGLE')
            ->set('formNombre', 'Toggle')
            ->call('guardarEquipo');

        $id = (int) DB::table('equipos')
            ->where('proyecto_id', $proyectoId)->where('codigo', 'EQ_TOGGLE')->value('id');

        Livewire::test(AdminEquiposProyecto::class)->call('desactivar', $id);
        $this->assertFalse((bool) DB::table('equipos')->where('id', $id)->value('activo'));

        Livewire::test(AdminEquiposProyecto::class)->call('activar', $id);
        $this->assertTrue((bool) DB::table('equipos')->where('id', $id)->value('activo'));
    }

    private function proyectoId(): int
    {
        return (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
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
            'email' => strtolower($codigoRol).'.'.Str::random(6).'@crm.local',
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
