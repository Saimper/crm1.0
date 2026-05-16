<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Usuarios\RolMandante;

use App\Models\User;
use App\Modules\Tenancy\Infrastructure\Http\Livewire\AdminProyectos;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class AdminProyectosScopeMandanteTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_mandante_solo_ve_proyectos_de_su_mandante(): void
    {
        $mandanteA = $this->crearMandante();
        $mandanteB = $this->crearMandante();
        $proyectoA1 = $this->crearProyectoCobranza($mandanteA);
        $proyectoA2 = $this->crearProyectoCx($mandanteA);
        $this->crearProyectoCobranza($mandanteB);

        $admin = $this->crearAdminMandante($mandanteA);

        $component = Livewire::actingAs($admin)->test(AdminProyectos::class);
        $proyectos = $component->viewData('proyectos');

        $this->assertSame(2, count($proyectos));
        $ids = array_map(fn ($p): int => (int) $p->id, $proyectos->all());
        sort($ids);
        $expected = [(int) $proyectoA1->id, (int) $proyectoA2->id];
        sort($expected);
        $this->assertSame($expected, $ids);
    }

    public function test_admin_mandante_solo_ve_su_mandante_en_lista_form(): void
    {
        $mandanteA = $this->crearMandante();
        $mandanteB = $this->crearMandante();
        $admin = $this->crearAdminMandante($mandanteA);

        $component = Livewire::actingAs($admin)->test(AdminProyectos::class);
        $mandantes = $component->viewData('mandantes');

        $this->assertSame(1, count($mandantes));
        $this->assertSame((int) $mandanteA->id, (int) $mandantes->first()->id);
    }

    public function test_admin_mandante_no_puede_editar_proyecto_ajeno(): void
    {
        $mandanteA = $this->crearMandante();
        $mandanteB = $this->crearMandante();
        $proyectoB = $this->crearProyectoCobranza($mandanteB);
        $admin = $this->crearAdminMandante($mandanteA);

        Livewire::actingAs($admin)
            ->test(AdminProyectos::class)
            ->call('abrirFormEditar', (int) $proyectoB->id)
            ->assertForbidden();
    }

    public function test_admin_mandante_no_puede_desactivar_proyecto_ajeno(): void
    {
        $mandanteA = $this->crearMandante();
        $mandanteB = $this->crearMandante();
        $proyectoB = $this->crearProyectoCobranza($mandanteB);
        $admin = $this->crearAdminMandante($mandanteA);

        Livewire::actingAs($admin)
            ->test(AdminProyectos::class)
            ->call('desactivar', (int) $proyectoB->id)
            ->assertForbidden();
    }

    public function test_admin_mandante_pre_selecciona_su_mandante_al_crear(): void
    {
        $mandanteA = $this->crearMandante();
        $admin = $this->crearAdminMandante($mandanteA);

        $component = Livewire::actingAs($admin)
            ->test(AdminProyectos::class)
            ->call('abrirFormCrear');

        $this->assertSame((int) $mandanteA->id, (int) $component->get('form.mandante_id'));
    }

    public function test_admin_mandante_no_puede_crear_proyecto_en_otro_mandante(): void
    {
        $mandanteA = $this->crearMandante();
        $mandanteB = $this->crearMandante();
        $admin = $this->crearAdminMandante($mandanteA);

        Livewire::actingAs($admin)
            ->test(AdminProyectos::class)
            ->call('abrirFormCrear')
            ->set('form.mandante_id', (int) $mandanteB->id)
            ->set('form.codigo', 'TEST_AJENO')
            ->set('form.nombre', 'Test Ajeno')
            ->set('form.tipo_operacion', 'cobranza')
            ->call('guardar')
            ->assertForbidden();
    }

    public function test_admin_global_ve_todos_los_proyectos(): void
    {
        $mandanteA = $this->crearMandante();
        $mandanteB = $this->crearMandante();
        $this->crearProyectoCobranza($mandanteA);
        $this->crearProyectoCobranza($mandanteB);
        $admin = $this->crearAdminGlobal();

        $component = Livewire::actingAs($admin)->test(AdminProyectos::class);
        $proyectos = $component->viewData('proyectos');

        // Cuenta puede incluir proyectos demo del seeder + 2 nuevos.
        $this->assertGreaterThanOrEqual(2, count($proyectos));
    }

    private function crearAdminMandante(\stdClass $mandante): User
    {
        /** @var User $u */
        $u = User::query()->create([
            'name' => 'Admin Mandante',
            'email' => 'admin.mand.'.Str::random(6).'@crm.local',
            'password' => Hash::make('x'),
            'activo' => true,
        ]);

        $rolId = (int) DB::table('roles')->where('codigo', 'ADMIN_MANDANTE')->value('id');
        DB::table('usuario_mandante_rol')->insert([
            'usuario_id' => $u->id,
            'mandante_id' => $mandante->id,
            'rol_id' => $rolId,
            'activo' => true,
        ]);

        return $u;
    }
}
