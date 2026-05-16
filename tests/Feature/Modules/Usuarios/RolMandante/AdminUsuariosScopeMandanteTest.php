<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Usuarios\RolMandante;

use App\Models\User;
use App\Modules\Usuarios\Infrastructure\Http\Livewire\AdminUsuarios;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class AdminUsuariosScopeMandanteTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_mandante_solo_ve_usuarios_con_pivot_en_sus_proyectos(): void
    {
        $mandanteA = $this->crearMandante();
        $mandanteB = $this->crearMandante();
        $proyectoA = $this->crearProyectoCobranza($mandanteA);
        $proyectoB = $this->crearProyectoCobranza($mandanteB);

        $admin = $this->crearAdminMandante($mandanteA);
        $gestorA = $this->crearGestor($proyectoA);
        $gestorB = $this->crearGestor($proyectoB);

        $component = Livewire::actingAs($admin)->test(AdminUsuarios::class);
        $usuarios = $component->viewData('usuarios');
        $ids = array_map(fn ($u): int => (int) $u->id, $usuarios->all());

        $this->assertContains($gestorA->id, $ids, 'Debe ver gestorA del mandante.');
        $this->assertNotContains($gestorB->id, $ids, 'NO debe ver gestorB de otro mandante.');
    }

    public function test_admin_mandante_solo_ve_proyectos_de_su_mandante_en_dropdown(): void
    {
        $mandanteA = $this->crearMandante();
        $mandanteB = $this->crearMandante();
        $proyectoA = $this->crearProyectoCobranza($mandanteA);
        $this->crearProyectoCobranza($mandanteB);

        $admin = $this->crearAdminMandante($mandanteA);

        $component = Livewire::actingAs($admin)->test(AdminUsuarios::class);
        $proyectos = $component->viewData('proyectos');
        $ids = array_map(fn ($p): int => (int) $p->id, $proyectos->all());

        $this->assertSame([(int) $proyectoA->id], $ids);
    }

    public function test_admin_mandante_solo_ve_asignaciones_de_su_mandante(): void
    {
        $mandanteA = $this->crearMandante();
        $mandanteB = $this->crearMandante();
        $proyectoA = $this->crearProyectoCobranza($mandanteA);
        $proyectoB = $this->crearProyectoCobranza($mandanteB);

        $admin = $this->crearAdminMandante($mandanteA);
        $gestorA = $this->crearGestor($proyectoA);
        $gestorB = $this->crearGestor($proyectoB);

        $component = Livewire::actingAs($admin)->test(AdminUsuarios::class);
        $asignaciones = $component->viewData('asignaciones');

        // Asignaciones del gestor B (mandante B) NO deben aparecer.
        $this->assertFalse($asignaciones->has($gestorB->id));
        $this->assertTrue($asignaciones->has($gestorA->id));
    }

    public function test_admin_mandante_no_puede_quitar_asignacion_de_proyecto_ajeno(): void
    {
        $mandanteA = $this->crearMandante();
        $mandanteB = $this->crearMandante();
        $proyectoB = $this->crearProyectoCobranza($mandanteB);
        $admin = $this->crearAdminMandante($mandanteA);
        $gestorB = $this->crearGestor($proyectoB);
        $rolGestorId = (int) DB::table('roles')->where('codigo', 'GESTOR')->value('id');

        Livewire::actingAs($admin)
            ->test(AdminUsuarios::class)
            ->call('quitarAsignacion', $gestorB->id, (int) $proyectoB->id, $rolGestorId)
            ->assertForbidden();
    }

    public function test_admin_mandante_no_puede_promover_admin_global(): void
    {
        $mandante = $this->crearMandante();
        $admin = $this->crearAdminMandante($mandante);
        $proyecto = $this->crearProyectoCobranza($mandante);
        $gestor = $this->crearGestor($proyecto);

        Livewire::actingAs($admin)
            ->test(AdminUsuarios::class)
            ->call('promoverAdminGlobal', $gestor->id)
            ->assertForbidden();
    }

    public function test_admin_global_ve_todos_los_usuarios(): void
    {
        $mandanteA = $this->crearMandante();
        $mandanteB = $this->crearMandante();
        $proyectoA = $this->crearProyectoCobranza($mandanteA);
        $proyectoB = $this->crearProyectoCobranza($mandanteB);
        $this->crearGestor($proyectoA);
        $this->crearGestor($proyectoB);

        $admin = $this->crearAdminGlobal();
        $component = Livewire::actingAs($admin)->test(AdminUsuarios::class);
        $usuarios = $component->viewData('usuarios');

        // Admin global ve todos: 2 gestores nuevos + admin global + posibles seeded.
        $this->assertGreaterThanOrEqual(3, count($usuarios));
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
