<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Usuarios;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

final class PermisosMultiProyectoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->markTestSkipped('TODO F35: migrar a factories tras limpieza demo seeders (ver tests/Support/EscenarioOperativo).');

    }

    public function test_admin_global_pasa_cualquier_permiso_sin_proyecto_activo(): void
    {
        $admin = User::factory()->create();
        $this->asignarGlobal($admin->id, 'ADMIN_GLOBAL');

        $this->assertTrue($admin->esAdminGlobal());
        $this->assertTrue(Gate::forUser($admin)->allows('gestiones.crear'));
        $this->assertTrue(Gate::forUser($admin)->allows('reportes.operativos'));
    }

    public function test_gestor_puede_gestiones_crear_en_su_proyecto(): void
    {
        $gestor = User::factory()->create();
        $proyectoId = $this->idProyectoDemo();
        $this->asignarAProyecto($gestor->id, $proyectoId, 'GESTOR');

        $this->setProyectoActivo($proyectoId);

        $this->assertTrue(Gate::forUser($gestor)->allows('gestiones.crear'));
    }

    public function test_gestor_no_puede_acceder_a_otro_proyecto_aunque_use_el_mismo_permiso(): void
    {
        $gestor = User::factory()->create();
        $proyectoA = $this->idProyectoDemo();
        $this->asignarAProyecto($gestor->id, $proyectoA, 'GESTOR');

        // Crear un proyecto B adicional del mismo mandante.
        $mandanteId = (int) DB::table('mandantes')->where('codigo', 'BPO_DEMO')->value('id');
        $proyectoB = (int) DB::table('proyectos')->insertGetId([
            'public_id' => '01HXPRBALTERN0000000000A',
            'mandante_id' => $mandanteId,
            'codigo' => 'OTRO_COB_2026',
            'nombre' => 'Otro proyecto cobranza',
            'tipo_operacion' => 'cobranza',
            'activo' => true,
        ]);

        $this->setProyectoActivo($proyectoB);

        $this->assertFalse(
            Gate::forUser($gestor)->allows('gestiones.crear'),
            'El gestor solo está asignado al proyecto A, no debería poder gestionar en proyecto B.',
        );
        $this->assertFalse($gestor->tieneAccesoAProyecto($proyectoB));
        $this->assertTrue($gestor->tieneAccesoAProyecto($proyectoA));
    }

    public function test_usuario_sin_proyecto_activo_retorna_false(): void
    {
        $gestor = User::factory()->create();
        $proyectoId = $this->idProyectoDemo();
        $this->asignarAProyecto($gestor->id, $proyectoId, 'GESTOR');

        $this->limpiarProyectoActivo();

        $this->assertFalse(Gate::forUser($gestor)->allows('gestiones.crear'));
    }

    public function test_auditor_no_puede_gestiones_crear(): void
    {
        $auditor = User::factory()->create();
        $proyectoId = $this->idProyectoDemo();
        $this->asignarAProyecto($auditor->id, $proyectoId, 'AUDITOR');

        $this->setProyectoActivo($proyectoId);

        $this->assertTrue(Gate::forUser($auditor)->allows('gestiones.ver'));
        $this->assertFalse(Gate::forUser($auditor)->allows('gestiones.crear'));
    }

    private function idProyectoDemo(): int
    {
        return (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
    }

    private function asignarGlobal(int $usuarioId, string $rolCodigo): void
    {
        $rolId = (int) DB::table('roles')->where('codigo', $rolCodigo)->value('id');
        DB::table('usuario_global_rol')->insert([
            'usuario_id' => $usuarioId,
            'rol_id' => $rolId,
        ]);
    }

    private function asignarAProyecto(int $usuarioId, int $proyectoId, string $rolCodigo): void
    {
        $rolId = (int) DB::table('roles')->where('codigo', $rolCodigo)->value('id');
        DB::table('usuario_proyecto_rol')->insert([
            'usuario_id' => $usuarioId,
            'proyecto_id' => $proyectoId,
            'rol_id' => $rolId,
            'activo' => true,
        ]);
    }

    private function setProyectoActivo(int $proyectoId): void
    {
        $proyecto = DB::table('proyectos')->find($proyectoId);
        $this->app->instance('tenancy.proyecto_activo', $proyecto);
    }

    private function limpiarProyectoActivo(): void
    {
        $this->app->forgetInstance('tenancy.proyecto_activo');
    }
}
