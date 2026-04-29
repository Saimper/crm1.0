<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tenancy;

use App\Models\User;
use App\Modules\Tenancy\Infrastructure\Http\Livewire\AdminMandantes;
use Database\Seeders\Tenancy\MandantesDemoSeeder;
use Database\Seeders\Tenancy\ProyectosDemoSeeder;
use Database\Seeders\Usuarios\PermisosSeeder;
use Database\Seeders\Usuarios\RolesSeeder;
use Database\Seeders\Usuarios\RolPermisoSeeder;
use Database\Seeders\Usuarios\UsuarioAdminGlobalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

final class AdminMandantesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            MandantesDemoSeeder::class,
            ProyectosDemoSeeder::class,
            RolesSeeder::class,
            PermisosSeeder::class,
            RolPermisoSeeder::class,
            UsuarioAdminGlobalSeeder::class,
        ]);
    }

    public function test_admin_global_crea_mandante(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(AdminMandantes::class)
            ->call('abrirFormCrear')
            ->assertSet('formVisible', true)
            ->set('form.codigo', 'BANCO_X')
            ->set('form.nombre', 'Banco X S.A.')
            ->set('form.documento', '1799123456001')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('formVisible', false);

        $this->assertDatabaseHas('mandantes', [
            'codigo'    => 'BANCO_X',
            'nombre'    => 'Banco X S.A.',
            'documento' => '1799123456001',
            'activo'    => true,
        ]);
    }

    public function test_admin_rechaza_codigo_duplicado(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(AdminMandantes::class)
            ->call('abrirFormCrear')
            ->set('form.codigo', 'BPO_DEMO')          // ya existe por MandantesDemoSeeder
            ->set('form.nombre', 'Duplicado')
            ->call('guardar')
            ->assertHasErrors(['form.codigo']);
    }

    public function test_admin_edita_nombre_mandante(): void
    {
        $this->actingAs($this->admin());
        $id = (int) DB::table('mandantes')->where('codigo', 'BPO_DEMO')->value('id');

        Livewire::test(AdminMandantes::class)
            ->call('abrirFormEditar', $id)
            ->set('form.nombre', 'BPO Demo Corp ACTUALIZADO')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('mandantes', [
            'id'     => $id,
            'nombre' => 'BPO Demo Corp ACTUALIZADO',
        ]);
    }

    public function test_admin_desactiva_y_reactiva_mandante(): void
    {
        $this->actingAs($this->admin());
        $id = (int) DB::table('mandantes')->where('codigo', 'BPO_DEMO')->value('id');

        Livewire::test(AdminMandantes::class)->call('desactivar', $id);
        $this->assertFalse((bool) DB::table('mandantes')->where('id', $id)->value('activo'));

        Livewire::test(AdminMandantes::class)->call('activar', $id);
        $this->assertTrue((bool) DB::table('mandantes')->where('id', $id)->value('activo'));
    }

    public function test_ruta_rechaza_no_admin_global(): void
    {
        $user = User::query()->create([
            'name' => 'Gestor', 'email' => 'g.'.Str::random(6).'@crm.local',
            'password' => Hash::make('x'), 'activo' => true,
        ]);

        $this->actingAs($user)->get(route('admin.mandantes'))->assertStatus(403);
    }

    public function test_ruta_200_para_admin_global(): void
    {
        $this->actingAs($this->admin())->get(route('admin.mandantes'))->assertStatus(200);
    }

    private function admin(): User
    {
        /** @var User $u */
        $u = User::query()->where('email', 'admin@crm.local')->firstOrFail();

        return $u;
    }
}
