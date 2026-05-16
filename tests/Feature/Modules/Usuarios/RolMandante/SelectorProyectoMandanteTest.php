<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Usuarios\RolMandante;

use App\Models\User;
use App\Modules\Tenancy\Infrastructure\Http\Livewire\SelectorProyecto;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class SelectorProyectoMandanteTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_mandante_ve_todos_los_proyectos_de_su_mandante(): void
    {
        $mandante = $this->crearMandante();
        $proyectoA = $this->crearProyectoCobranza($mandante);
        $proyectoB = $this->crearProyectoCx($mandante);

        $admin = $this->crearAdminMandante($mandante);

        $component = Livewire::actingAs($admin)
            ->withQueryParams(['mandante' => (string) $mandante->id])
            ->test(SelectorProyecto::class);

        $proyectos = $component->viewData('proyectos');

        $this->assertSame(2, count($proyectos));
        $ids = array_map(fn ($p): int => (int) $p->id, $proyectos->all());
        sort($ids);
        $expected = [(int) $proyectoA->id, (int) $proyectoB->id];
        sort($expected);
        $this->assertSame($expected, $ids);
    }

    public function test_admin_mandante_no_ve_proyectos_de_otros_mandantes(): void
    {
        $mandanteA = $this->crearMandante();
        $mandanteB = $this->crearMandante();
        // Dos proyectos en A para evitar el redirect-on-single del mount.
        $proyectoA1 = $this->crearProyectoCobranza($mandanteA);
        $proyectoA2 = $this->crearProyectoCx($mandanteA);
        $this->crearProyectoCobranza($mandanteB);

        $admin = $this->crearAdminMandante($mandanteA);

        $component = Livewire::actingAs($admin)->test(SelectorProyecto::class);

        $proyectos = $component->viewData('proyectos');
        $this->assertSame(2, count($proyectos));
        $ids = array_map(fn ($p): int => (int) $p->id, $proyectos->all());
        sort($ids);
        $expected = [(int) $proyectoA1->id, (int) $proyectoA2->id];
        sort($expected);
        $this->assertSame($expected, $ids);
    }

    public function test_admin_mandante_con_un_solo_proyecto_redirige_directo(): void
    {
        $mandante = $this->crearMandante();
        $proyecto = $this->crearProyectoCobranza($mandante);
        $admin = $this->crearAdminMandante($mandante);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertRedirect("/proyectos/{$proyecto->id}");
    }

    public function test_admin_mandante_accede_a_ruta_proyecto_sin_pivot_proyecto(): void
    {
        $mandante = $this->crearMandante();
        $proyecto = $this->crearProyectoCobranza($mandante);
        $admin = $this->crearAdminMandante($mandante);

        // Sin pivot en usuario_proyecto_rol; solo via usuario_mandante_rol.
        $this->assertSame(0, DB::table('usuario_proyecto_rol')->where('usuario_id', $admin->id)->count());

        $this->actingAs($admin)
            ->get("/proyectos/{$proyecto->id}/casos")
            ->assertOk();
    }

    public function test_admin_mandante_no_accede_a_proyecto_de_otro_mandante(): void
    {
        $mandanteA = $this->crearMandante();
        $mandanteB = $this->crearMandante();
        $proyectoB = $this->crearProyectoCobranza($mandanteB);
        $admin = $this->crearAdminMandante($mandanteA);

        $this->actingAs($admin)
            ->get("/proyectos/{$proyectoB->id}/casos")
            ->assertStatus(403);
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
