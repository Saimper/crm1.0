<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Reportes;

use App\Models\User;
use App\Modules\Reportes\Infrastructure\Http\Livewire\DashboardAnalitico;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

final class ReportesAnaliticosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->markTestSkipped('TODO F35: migrar a factories tras limpieza demo seeders (ver tests/Support/EscenarioOperativo).');

    }

    public function test_supervisor_accede_ruta_analiticos(): void
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');

        $this->actingAs($supervisor)
            ->get(route('proyectos.reportes.analiticos', ['proyecto_id' => $proyectoId]))
            ->assertStatus(200);
    }

    public function test_gestor_recibe_403_en_analiticos(): void
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        $gestor = $this->crearConRol($proyectoId, 'GESTOR');

        $this->actingAs($gestor)
            ->get(route('proyectos.reportes.analiticos', ['proyecto_id' => $proyectoId]))
            ->assertStatus(403);
    }

    public function test_componente_render_con_datos(): void
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        $this->app->instance('tenancy.proyecto_activo', DB::table('proyectos')->find($proyectoId));

        $this->actingAs($this->crearConRol($proyectoId, 'SUPERVISOR'));

        Livewire::test(DashboardAnalitico::class)
            ->assertViewHas('distribucionCasos')
            ->assertViewHas('compromisosPorEstado')
            ->assertViewHas('efectividadPorResultado');
    }

    private function crearConRol(int $proyectoId, string $codigoRol): User
    {
        /** @var User $u */
        $u = User::query()->create([
            'name' => ucfirst(strtolower($codigoRol)),
            'email' => strtolower($codigoRol).'.'.Str::random(6).'@crm.local',
            'password' => Hash::make('x'), 'activo' => true,
        ]);
        $rolId = (int) DB::table('roles')->where('codigo', $codigoRol)->value('id');
        DB::table('usuario_proyecto_rol')->insert([
            'usuario_id' => $u->id, 'proyecto_id' => $proyectoId, 'rol_id' => $rolId, 'activo' => true,
        ]);

        return $u;
    }
}
