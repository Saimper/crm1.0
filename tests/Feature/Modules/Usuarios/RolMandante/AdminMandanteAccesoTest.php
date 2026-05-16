<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Usuarios\RolMandante;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * F39: admin_mandante reusa pantallas admin globales filtradas por mandante.
 * Verifica acceso a rutas compartidas y bloqueo a rutas exclusivas ADMIN_GLOBAL.
 */
final class AdminMandanteAccesoTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_mandante_accede_a_dashboard_admin(): void
    {
        $mandante = $this->crearMandante();
        $admin = $this->crearAdminMandante($mandante);

        $this->actingAs($admin)->get('/admin')->assertOk();
    }

    public function test_admin_mandante_accede_a_proyectos(): void
    {
        $mandante = $this->crearMandante();
        $admin = $this->crearAdminMandante($mandante);

        $this->actingAs($admin)->get('/admin/proyectos')->assertOk();
    }

    public function test_admin_mandante_accede_a_usuarios(): void
    {
        $mandante = $this->crearMandante();
        $admin = $this->crearAdminMandante($mandante);

        $this->actingAs($admin)->get('/admin/usuarios')->assertOk();
    }

    public function test_admin_mandante_accede_a_auditoria(): void
    {
        $mandante = $this->crearMandante();
        $admin = $this->crearAdminMandante($mandante);

        $this->actingAs($admin)->get('/admin/auditoria')->assertOk();
    }

    public function test_admin_mandante_n_o_accede_a_mandantes(): void
    {
        $mandante = $this->crearMandante();
        $admin = $this->crearAdminMandante($mandante);

        $this->actingAs($admin)->get('/admin/mandantes')->assertStatus(403);
    }

    public function test_admin_mandante_n_o_accede_a_campos_personalizados(): void
    {
        $mandante = $this->crearMandante();
        $admin = $this->crearAdminMandante($mandante);

        $this->actingAs($admin)->get('/admin/campos-personalizados')->assertStatus(403);
    }

    public function test_admin_mandante_n_o_accede_a_entidades_configurables(): void
    {
        $mandante = $this->crearMandante();
        $admin = $this->crearAdminMandante($mandante);

        $this->actingAs($admin)->get('/admin/entidades-configurables')->assertStatus(403);
    }

    public function test_admin_mandante_n_o_accede_a_sso_secrets(): void
    {
        $mandante = $this->crearMandante();
        $admin = $this->crearAdminMandante($mandante);

        $this->actingAs($admin)->get('/admin/integracion/secrets')->assertStatus(403);
    }

    public function test_gestor_no_accede_a_admin(): void
    {
        $mandante = $this->crearMandante();
        $proyecto = $this->crearProyectoCobranza($mandante);
        $gestor = $this->crearGestor($proyecto);

        $this->actingAs($gestor)->get('/admin')->assertStatus(403);
    }

    public function test_admin_global_sigue_accediendo_a_todo(): void
    {
        $admin = $this->crearAdminGlobal();

        $this->actingAs($admin)->get('/admin')->assertOk();
        $this->actingAs($admin)->get('/admin/proyectos')->assertOk();
        $this->actingAs($admin)->get('/admin/mandantes')->assertOk();
        $this->actingAs($admin)->get('/admin/usuarios')->assertOk();
        $this->actingAs($admin)->get('/admin/auditoria')->assertOk();
        $this->actingAs($admin)->get('/admin/campos-personalizados')->assertOk();
        $this->actingAs($admin)->get('/admin/entidades-configurables')->assertOk();
        $this->actingAs($admin)->get('/admin/integracion/secrets')->assertOk();
    }

    public function test_dashboard_oculta_tiles_vetados_a_admin_mandante(): void
    {
        $mandante = $this->crearMandante();
        $admin = $this->crearAdminMandante($mandante);

        $resp = $this->actingAs($admin)->get('/admin');
        $resp->assertOk()
            ->assertDontSee('Mandantes', false)
            ->assertDontSee('Campos personalizados', false)
            ->assertDontSee('Entidades configurables', false)
            ->assertSee('Proyectos')
            ->assertSee('Auditoría del mandante');
    }

    public function test_dashboard_admin_global_muestra_todos_los_tiles(): void
    {
        $admin = $this->crearAdminGlobal();

        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertSee('Mandantes')
            ->assertSee('Campos personalizados')
            ->assertSee('Entidades configurables')
            ->assertSee('Proyectos');
    }

    public function test_admin_mandante_accede_a_configurador_proyecto_de_su_mandante(): void
    {
        $mandante = $this->crearMandante();
        $proyecto = $this->crearProyectoCobranza($mandante);
        $admin = $this->crearAdminMandante($mandante);

        $this->actingAs($admin)
            ->get(route('admin.proyectos.configurar', ['proyecto' => $proyecto->public_id]))
            ->assertOk();
    }

    public function test_admin_mandante_no_accede_a_configurador_de_proyecto_ajeno(): void
    {
        $mandanteA = $this->crearMandante();
        $mandanteB = $this->crearMandante();
        $proyectoB = $this->crearProyectoCobranza($mandanteB);
        $admin = $this->crearAdminMandante($mandanteA);

        $this->actingAs($admin)
            ->get(route('admin.proyectos.configurar', ['proyecto' => $proyectoB->public_id]))
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
