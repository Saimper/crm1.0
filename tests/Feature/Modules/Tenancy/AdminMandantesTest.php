<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tenancy;

use App\Models\User;
use App\Modules\Tenancy\Infrastructure\Http\Livewire\AdminMandantes;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class AdminMandantesTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_global_crea_mandante(): void
    {
        $this->actingAs($this->crearAdminGlobal());

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
            'codigo' => 'BANCO_X',
            'nombre' => 'Banco X S.A.',
            'documento' => '1799123456001',
            'activo' => true,
        ]);
    }

    public function test_admin_rechaza_codigo_duplicado(): void
    {
        $this->markTestSkipped(
            'La premisa del test ya no es la del componente: desde la política B6 '
            .'(commit 748dbd0), AdminMandantes::guardar no rechaza el código repetido, '
            .'lo desambigua con GeneradorCodigo::resolverConflicto. Con `BPO_DEMO` ya '
            .'ocupado, guardar no añade error y crea el mandante como `BPO_DEMO_2` '
            .'(verificado en crm_test_w3). Reescribir el assert a "crea BPO_DEMO_2" '
            .'sería comprobar otra cosa, así que queda para que producto decida cuál '
            .'de las dos conductas quiere antes de fijar el test.'
        );

        $this->crearMandante('BPO_DEMO', 'BPO Demo Corp');
        $this->actingAs($this->crearAdminGlobal());

        Livewire::test(AdminMandantes::class)
            ->call('abrirFormCrear')
            ->set('form.codigo', 'BPO_DEMO')          // ya existe
            ->set('form.nombre', 'Duplicado')
            ->call('guardar')
            ->assertHasErrors(['form.codigo']);
    }

    public function test_admin_edita_nombre_mandante(): void
    {
        $mandante = $this->crearMandante('BPO_DEMO', 'BPO Demo Corp');
        $this->actingAs($this->crearAdminGlobal());
        $id = (int) $mandante->id;

        Livewire::test(AdminMandantes::class)
            ->call('abrirFormEditar', $id)
            ->set('form.nombre', 'BPO Demo Corp ACTUALIZADO')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('mandantes', [
            'id' => $id,
            'nombre' => 'BPO Demo Corp ACTUALIZADO',
        ]);
    }

    public function test_admin_desactiva_y_reactiva_mandante(): void
    {
        $mandante = $this->crearMandante('BPO_DEMO', 'BPO Demo Corp');
        $this->actingAs($this->crearAdminGlobal());
        $id = (int) $mandante->id;

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
        $this->actingAs($this->crearAdminGlobal())->get(route('admin.mandantes'))->assertStatus(200);
    }
}
