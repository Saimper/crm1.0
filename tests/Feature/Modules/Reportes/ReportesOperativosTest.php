<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Reportes;

use App\Models\User;
use App\Modules\Reportes\Infrastructure\Http\Livewire\DashboardOperativo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

final class ReportesOperativosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->markTestSkipped('TODO F35: migrar a factories tras limpieza demo seeders (ver tests/Support/EscenarioOperativo).');

    }

    public function test_supervisor_accede_ruta_reportes_operativos(): void
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        $supervisor = $this->crearUsuarioConRol($proyectoId, 'SUPERVISOR');

        $this->actingAs($supervisor)
            ->get(route('proyectos.reportes.operativos', ['proyecto_id' => $proyectoId]))
            ->assertStatus(200);
    }

    public function test_gestor_recibe_403_en_ruta_reportes_operativos(): void
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        $gestor = $this->crearUsuarioConRol($proyectoId, 'GESTOR');

        $this->actingAs($gestor)
            ->get(route('proyectos.reportes.operativos', ['proyecto_id' => $proyectoId]))
            ->assertStatus(403);
    }

    public function test_admin_global_accede_ruta_reportes(): void
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        $admin = User::query()->where('email', 'admin@crm.local')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('proyectos.reportes.operativos', ['proyecto_id' => $proyectoId]))
            ->assertStatus(200);
    }

    public function test_componente_dashboard_render_con_metricas_cero(): void
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        $this->app->instance('tenancy.proyecto_activo', DB::table('proyectos')->find($proyectoId));

        $supervisor = $this->crearUsuarioConRol($proyectoId, 'SUPERVISOR');
        $this->actingAs($supervisor);

        Livewire::test(DashboardOperativo::class)
            ->assertViewHas('cuentasIntentadas', 0)
            ->assertViewHas('cuentasGestionadas', 0)
            ->assertViewHas('totalGestiones', 0);
    }

    public function test_componente_aborta_403_si_usuario_sin_permiso(): void
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        $this->app->instance('tenancy.proyecto_activo', DB::table('proyectos')->find($proyectoId));

        $gestor = $this->crearUsuarioConRol($proyectoId, 'GESTOR');
        $this->actingAs($gestor);

        Livewire::test(DashboardOperativo::class)->assertStatus(403);
    }

    private function crearUsuarioConRol(int $proyectoId, string $codigoRol): User
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
