<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tenancy;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class AccesoProyectoTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    /**
     * El proyecto contra el que se prueba el acceso. Se monta en setUp y no
     * dentro de cada test porque también fija el ÚNICO mandante del escenario:
     * `mandante.activo` manda a elegir cliente cuando el usuario alcanza más de
     * uno, y los tests de /admin esperan la pantalla, no el selector.
     */
    private stdClass $proyecto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->proyecto = $this->crearProyectoCobranza();
    }

    public function test_usuario_sin_asignacion_recibe_403_al_entrar_a_proyecto(): void
    {
        $gestor = User::factory()->create();

        $this->actingAs($gestor)
            ->get("/proyectos/{$this->idProyecto()}")
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

        $this->actingAs($admin)
            ->get("/proyectos/{$this->idProyecto()}")
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
        return (int) $this->proyecto->id;
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
