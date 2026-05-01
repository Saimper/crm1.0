<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Integracion;

use App\Models\User;
use App\Modules\Integracion\Infrastructure\Http\Livewire\AdminSsoSecrets;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

final class AdminSsoSecretsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_global_accede_a_pantalla_y_ve_proyectos(): void
    {
        $admin = $this->crearAdminGlobal();

        $this->actingAs($admin)
            ->get('/admin/integracion/secrets')
            ->assertOk()
            ->assertSee('SSO secrets')
            ->assertSee('COBRANZA_DEMO_2026');
    }

    public function test_no_admin_recibe_403_o_redirect(): void
    {
        $usuario = $this->crearGestorEnProyecto();

        $response = $this->actingAs($usuario)->get('/admin/integracion/secrets');

        $this->assertContains($response->status(), [302, 403]);
    }

    public function test_secret_se_enmascara_por_defecto_en_render(): void
    {
        $admin = $this->crearAdminGlobal();

        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        $secretReal = (string) DB::table('proyectos')->where('id', $proyectoId)->value('sso_secret');

        $component = Livewire::actingAs($admin)->test(AdminSsoSecrets::class);

        $html = $component->html();
        $this->assertStringNotContainsString($secretReal, $html, 'El secret completo no debe filtrarse en render inicial.');
        $this->assertStringContainsString(substr($secretReal, -8), $html, 'Solo se muestran últimos 8 chars enmascarados.');
    }

    public function test_revelar_muestra_secret_completo(): void
    {
        $admin = $this->crearAdminGlobal();
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        $secretReal = (string) DB::table('proyectos')->where('id', $proyectoId)->value('sso_secret');

        Livewire::actingAs($admin)
            ->test(AdminSsoSecrets::class)
            ->call('revelar', $proyectoId)
            ->assertSee($secretReal);
    }

    public function test_rotar_cambia_secret_en_bd_y_lo_muestra_una_vez(): void
    {
        $admin = $this->crearAdminGlobal();
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        $secretAntes = (string) DB::table('proyectos')->where('id', $proyectoId)->value('sso_secret');

        Livewire::actingAs($admin)
            ->test(AdminSsoSecrets::class)
            ->call('rotar', $proyectoId)
            ->assertSet('rotadoId', $proyectoId);

        $secretDespues = (string) DB::table('proyectos')->where('id', $proyectoId)->value('sso_secret');
        $this->assertNotSame($secretAntes, $secretDespues);
        $this->assertSame(64, strlen($secretDespues));
    }

    private function crearAdminGlobal(): User
    {
        /** @var User $u */
        $u = User::query()->create([
            'name' => 'Admin SSO',
            'email' => 'admin.sso.'.Str::random(6).'@crm.local',
            'password' => Hash::make('x'),
            'activo' => true,
        ]);

        $rolAdminId = (int) DB::table('roles')->where('codigo', 'ADMIN_GLOBAL')->value('id');
        DB::table('usuario_global_rol')->insert([
            'usuario_id' => $u->id,
            'rol_id' => $rolAdminId,
        ]);

        return $u;
    }

    private function crearGestorEnProyecto(): User
    {
        /** @var User $u */
        $u = User::query()->create([
            'name' => 'Gestor SSO',
            'email' => 'gestor.sso.'.Str::random(6).'@crm.local',
            'password' => Hash::make('x'),
            'activo' => true,
        ]);

        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        $rolGestorId = (int) DB::table('roles')->where('codigo', 'GESTOR')->value('id');
        DB::table('usuario_proyecto_rol')->insert([
            'usuario_id' => $u->id,
            'proyecto_id' => $proyectoId,
            'rol_id' => $rolGestorId,
            'activo' => true,
        ]);

        return $u;
    }
}
