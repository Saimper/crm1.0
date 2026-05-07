<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Usuarios;

use App\Models\User;
use App\Modules\Usuarios\Infrastructure\Http\Livewire\AdminUsuarios;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

final class AdminUsuariosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->markTestSkipped('TODO F35: migrar a factories tras limpieza demo seeders (ver tests/Support/EscenarioOperativo).');

    }

    public function test_admin_crea_usuario(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(AdminUsuarios::class)
            ->call('abrirFormCrearUsuario')
            ->set('formUsuario.name', 'Nuevo Tester')
            ->set('formUsuario.email', 'nuevo@crm.local')
            ->set('formUsuario.password', 'secret12')
            ->call('guardarUsuario')
            ->assertHasNoErrors()
            ->assertSet('formUsuarioVisible', false);

        $this->assertDatabaseHas('users', [
            'email' => 'nuevo@crm.local',
            'name' => 'Nuevo Tester',
            'activo' => true,
        ]);
    }

    public function test_admin_edita_usuario_sin_cambiar_password(): void
    {
        $this->actingAs($this->admin());
        $user = User::query()->create([
            'name' => 'Antes', 'email' => 'edit@crm.local',
            'password' => Hash::make('previa12'), 'activo' => true,
        ]);
        $hashPrevio = (string) User::query()->find($user->id)->password;

        Livewire::test(AdminUsuarios::class)
            ->call('abrirFormEditarUsuario', $user->id)
            ->set('formUsuario.name', 'Despues')
            ->set('formUsuario.password', '')
            ->call('guardarUsuario')
            ->assertHasNoErrors();

        $hashFinal = (string) User::query()->find($user->id)->password;
        $this->assertSame('Despues', User::query()->find($user->id)->name);
        $this->assertSame($hashPrevio, $hashFinal, 'La contraseña no debe cambiar si se deja vacía');
    }

    public function test_admin_rechaza_email_duplicado(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(AdminUsuarios::class)
            ->call('abrirFormCrearUsuario')
            ->set('formUsuario.name', 'Dup')
            ->set('formUsuario.email', 'admin@crm.local')       // ya existe
            ->set('formUsuario.password', 'secret12')
            ->call('guardarUsuario')
            ->assertHasErrors(['formUsuario.email']);
    }

    public function test_promover_y_revocar_admin_global(): void
    {
        $this->actingAs($this->admin());
        $user = User::query()->create([
            'name' => 'Aspirante', 'email' => 'asp.'.Str::random(4).'@crm.local',
            'password' => Hash::make('x'), 'activo' => true,
        ]);

        Livewire::test(AdminUsuarios::class)->call('promoverAdminGlobal', $user->id);
        $this->assertTrue($user->fresh()->esAdminGlobal());

        Livewire::test(AdminUsuarios::class)->call('revocarAdminGlobal', $user->id);
        $this->assertFalse($user->fresh()->esAdminGlobal());
    }

    public function test_no_puede_revocarse_a_si_mismo(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        Livewire::test(AdminUsuarios::class)->call('revocarAdminGlobal', $admin->id);

        $this->assertTrue($admin->fresh()->esAdminGlobal(), 'El admin no debe quedar sin rol');
    }

    public function test_asignar_y_quitar_rol_en_proyecto(): void
    {
        $this->actingAs($this->admin());

        $user = User::query()->create([
            'name' => 'Asignable', 'email' => 'asn.'.Str::random(4).'@crm.local',
            'password' => Hash::make('x'), 'activo' => true,
        ]);
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        $rolGestorId = (int) DB::table('roles')->where('codigo', 'GESTOR')->value('id');

        Livewire::test(AdminUsuarios::class)
            ->call('abrirFormAsignacion', $user->id)
            ->set('asignarProyectoId', $proyectoId)
            ->set('asignarRolId', $rolGestorId)
            ->call('guardarAsignacion')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('usuario_proyecto_rol', [
            'usuario_id' => $user->id,
            'proyecto_id' => $proyectoId,
            'rol_id' => $rolGestorId,
            'activo' => true,
        ]);

        Livewire::test(AdminUsuarios::class)
            ->call('quitarAsignacion', $user->id, $proyectoId, $rolGestorId);

        $this->assertDatabaseMissing('usuario_proyecto_rol', [
            'usuario_id' => $user->id,
            'proyecto_id' => $proyectoId,
            'rol_id' => $rolGestorId,
        ]);
    }

    public function test_ruta_rechaza_no_admin_global(): void
    {
        $user = User::query()->create([
            'name' => 'X', 'email' => 'x.'.Str::random(6).'@crm.local',
            'password' => Hash::make('x'), 'activo' => true,
        ]);
        $this->actingAs($user)->get(route('admin.usuarios'))->assertStatus(403);
    }

    public function test_ruta_200_admin_global(): void
    {
        $this->actingAs($this->admin())->get(route('admin.usuarios'))->assertStatus(200);
    }

    private function admin(): User
    {
        /** @var User $u */
        $u = User::query()->where('email', 'admin@crm.local')->firstOrFail();

        return $u;
    }
}
