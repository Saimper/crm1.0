<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\CamposPersonalizados;

use App\Models\User;
use App\Modules\CamposPersonalizados\Infrastructure\Http\Livewire\AdminCamposPersonalizados;
use Database\Seeders\Gestiones\GestionesCatalogosDemoSeeder;
use Database\Seeders\Tenancy\CarterasDemoSeeder;
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

final class AdminCamposPersonalizadosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            MandantesDemoSeeder::class,
            ProyectosDemoSeeder::class,
            CarterasDemoSeeder::class,
            GestionesCatalogosDemoSeeder::class,
            RolesSeeder::class,
            PermisosSeeder::class,
            RolPermisoSeeder::class,
            UsuarioAdminGlobalSeeder::class,
        ]);
    }

    public function test_admin_crea_campo_personalizado(): void
    {
        $admin = $this->obtenerAdminGlobal();
        $this->actingAs($admin);

        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        $carteraId = (int) DB::table('carteras')->where('proyecto_id', $proyectoId)->where('codigo', 'CONSUMO')->value('id');

        Livewire::test(AdminCamposPersonalizados::class)
            ->call('abrirFormCrear')
            ->assertSet('formVisible', true)
            ->set('form.proyecto_id', $proyectoId)
            ->set('form.ambito', 'caso')
            ->set('form.ambito_id', $carteraId)
            ->set('form.codigo', 'observacion_gerente')
            ->set('form.etiqueta', 'Observación del gerente')
            ->set('form.tipo', 'texto_corto')
            ->set('form.longitud_max', 200)
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('formVisible', false);

        $this->assertDatabaseHas('campos_personalizados', [
            'proyecto_id' => $proyectoId,
            'ambito' => 'caso',
            'ambito_id' => $carteraId,
            'codigo' => 'observacion_gerente',
            'etiqueta' => 'Observación del gerente',
            'tipo' => 'texto_corto',
            'activo' => true,
        ]);
    }

    public function test_admin_rechaza_codigo_duplicado_en_mismo_ambito(): void
    {
        $this->actingAs($this->obtenerAdminGlobal());

        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        $carteraId = (int) DB::table('carteras')->where('proyecto_id', $proyectoId)->where('codigo', 'CONSUMO')->value('id');

        DB::table('campos_personalizados')->insert([
            'proyecto_id' => $proyectoId,
            'ambito' => 'caso',
            'ambito_id' => $carteraId,
            'codigo' => 'existente',
            'etiqueta' => 'Ya existe',
            'tipo' => 'texto_corto',
            'obligatorio' => false,
            'activo' => true,
            'orden' => 100,
        ]);

        Livewire::test(AdminCamposPersonalizados::class)
            ->call('abrirFormCrear')
            ->set('form.proyecto_id', $proyectoId)
            ->set('form.ambito', 'caso')
            ->set('form.ambito_id', $carteraId)
            ->set('form.codigo', 'existente')
            ->set('form.etiqueta', 'Duplicado')
            ->set('form.tipo', 'texto_corto')
            ->call('guardar')
            ->assertHasErrors(['form.codigo']);
    }

    public function test_admin_rechaza_ambito_id_que_no_pertenece_al_proyecto(): void
    {
        $this->actingAs($this->obtenerAdminGlobal());

        $proyectoCob = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        $proyectoCx = (int) DB::table('proyectos')->where('codigo', 'SOPORTE_DEMO_2026')->value('id');
        $carteraCx = (int) DB::table('carteras')->where('proyecto_id', $proyectoCx)->value('id');

        Livewire::test(AdminCamposPersonalizados::class)
            ->call('abrirFormCrear')
            ->set('form.proyecto_id', $proyectoCob)
            ->set('form.ambito', 'caso')
            ->set('form.ambito_id', $carteraCx) // cartera de otro proyecto
            ->set('form.codigo', 'cruzado')
            ->set('form.etiqueta', 'Cruzado')
            ->set('form.tipo', 'texto_corto')
            ->call('guardar')
            ->assertHasErrors(['form.ambito_id']);

        $this->assertSame(0, DB::table('campos_personalizados')->where('codigo', 'cruzado')->count());
    }

    public function test_admin_desactiva_y_reactiva_campo(): void
    {
        $this->actingAs($this->obtenerAdminGlobal());

        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        $carteraId = (int) DB::table('carteras')->where('proyecto_id', $proyectoId)->value('id');
        $campoId = (int) DB::table('campos_personalizados')->insertGetId([
            'proyecto_id' => $proyectoId, 'ambito' => 'caso', 'ambito_id' => $carteraId,
            'codigo' => 'campo_demo', 'etiqueta' => 'Demo', 'tipo' => 'texto_corto',
            'obligatorio' => false, 'activo' => true, 'orden' => 100,
        ]);

        Livewire::test(AdminCamposPersonalizados::class)
            ->call('desactivar', $campoId);
        $this->assertFalse((bool) DB::table('campos_personalizados')->where('id', $campoId)->value('activo'));

        Livewire::test(AdminCamposPersonalizados::class)
            ->call('activar', $campoId);
        $this->assertTrue((bool) DB::table('campos_personalizados')->where('id', $campoId)->value('activo'));
    }

    public function test_ruta_admin_rechaza_a_no_admin_global(): void
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        $user = User::query()->create([
            'name' => 'Gestor', 'email' => 'g.'.Str::random(6).'@crm.local',
            'password' => Hash::make('x'), 'activo' => true,
        ]);
        $rolGestor = (int) DB::table('roles')->where('codigo', 'GESTOR')->value('id');
        DB::table('usuario_proyecto_rol')->insert([
            'usuario_id' => $user->id, 'proyecto_id' => $proyectoId,
            'rol_id' => $rolGestor, 'activo' => true,
        ]);

        $this->actingAs($user)
            ->get(route('admin.campos-personalizados'))
            ->assertStatus(403);
    }

    public function test_ruta_admin_responde_200_a_admin_global(): void
    {
        $this->actingAs($this->obtenerAdminGlobal())
            ->get(route('admin.campos-personalizados'))
            ->assertStatus(200);
    }

    private function obtenerAdminGlobal(): User
    {
        /** @var User $u */
        $u = User::query()->where('email', 'admin@crm.local')->firstOrFail();

        return $u;
    }
}
