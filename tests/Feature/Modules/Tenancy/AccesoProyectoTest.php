<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tenancy;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class AccesoProyectoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->markTestSkipped('TODO F35: migrar a factories tras limpieza demo seeders (ver tests/Support/EscenarioOperativo).');

    }

    public function test_usuario_sin_asignacion_recibe_403_al_entrar_a_proyecto(): void
    {
        $gestor = User::factory()->create();
        $proyectoId = $this->idProyecto();

        $this->actingAs($gestor)
            ->get("/proyectos/{$proyectoId}")
            ->assertForbidden();
    }

    public function test_usuario_asignado_accede_correctamente(): void
    {
        $gestor = User::factory()->create();
        $proyectoId = $this->idProyecto();
        $this->asignar($gestor->id, $proyectoId, 'GESTOR');

        $this->actingAs($gestor)
            ->get("/proyectos/{$proyectoId}")
            ->assertOk();
    }

    public function test_admin_global_accede_a_cualquier_proyecto(): void
    {
        $admin = User::factory()->create();
        $rolAdmin = (int) DB::table('roles')->where('codigo', 'ADMIN_GLOBAL')->value('id');
        DB::table('usuario_global_rol')->insert(['usuario_id' => $admin->id, 'rol_id' => $rolAdmin]);

        $proyectoId = $this->idProyecto();

        $this->actingAs($admin)
            ->get("/proyectos/{$proyectoId}")
            ->assertOk();
    }

    public function test_proyecto_inexistente_retorna_404(): void
    {
        $gestor = User::factory()->create();

        $this->actingAs($gestor)
            ->get('/proyectos/99999')
            ->assertNotFound();
    }

    public function test_gestor_no_puede_entrar_a_ruta_admin(): void
    {
        $gestor = User::factory()->create();
        $this->asignar($gestor->id, $this->idProyecto(), 'GESTOR');

        $this->actingAs($gestor)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_admin_global_accede_a_ruta_admin(): void
    {
        $admin = User::factory()->create();
        $rolAdmin = (int) DB::table('roles')->where('codigo', 'ADMIN_GLOBAL')->value('id');
        DB::table('usuario_global_rol')->insert(['usuario_id' => $admin->id, 'rol_id' => $rolAdmin]);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk();
    }

    private function idProyecto(): int
    {
        return (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
    }

    private function asignar(int $usuarioId, int $proyectoId, string $rolCodigo): void
    {
        $rolId = (int) DB::table('roles')->where('codigo', $rolCodigo)->value('id');
        DB::table('usuario_proyecto_rol')->insert([
            'usuario_id' => $usuarioId,
            'proyecto_id' => $proyectoId,
            'rol_id' => $rolId,
            'activo' => true,
        ]);
    }
}
