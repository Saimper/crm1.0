<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Usuarios;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class PermisosMultiProyectoTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_global_pasa_cualquier_permiso_sin_proyecto_activo(): void
    {
        $admin = $this->crearAdminGlobal();

        $this->limpiarProyectoActivo();

        $this->assertTrue($admin->esAdminGlobal());
        $this->assertTrue(Gate::forUser($admin)->allows('gestiones.crear'));
        $this->assertTrue(Gate::forUser($admin)->allows('reportes.operativos'));
    }

    public function test_gestor_puede_gestiones_crear_en_su_proyecto(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $gestor = $this->crearGestor($proyecto);

        $this->activarProyecto($proyecto);

        $this->assertTrue(Gate::forUser($gestor)->allows('gestiones.crear'));
    }

    public function test_gestor_no_puede_acceder_a_otro_proyecto_aunque_use_el_mismo_permiso(): void
    {
        // Dos proyectos del mismo mandante: el gestor solo está asignado al A.
        $mandante = $this->crearMandante();
        $proyectoA = $this->crearProyecto('cobranza', $mandante);
        $proyectoB = $this->crearProyecto('cobranza', $mandante);

        $gestor = $this->crearGestor($proyectoA);

        $this->activarProyecto($proyectoB);

        $this->assertFalse(
            Gate::forUser($gestor)->allows('gestiones.crear'),
            'El gestor solo está asignado al proyecto A, no debería poder gestionar en proyecto B.',
        );
        $this->assertFalse($gestor->tieneAccesoAProyecto((int) $proyectoB->id));
        $this->assertTrue($gestor->tieneAccesoAProyecto((int) $proyectoA->id));
    }

    public function test_usuario_sin_proyecto_activo_retorna_false(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $gestor = $this->crearGestor($proyecto);

        $this->limpiarProyectoActivo();

        $this->assertFalse(Gate::forUser($gestor)->allows('gestiones.crear'));
    }

    public function test_auditor_no_puede_gestiones_crear(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $auditor = $this->crearAuditor($proyecto);

        $this->activarProyecto($proyecto);

        $this->assertTrue(Gate::forUser($auditor)->allows('gestiones.ver'));
        $this->assertFalse(Gate::forUser($auditor)->allows('gestiones.crear'));
    }

    private function limpiarProyectoActivo(): void
    {
        $this->app->forgetInstance('tenancy.proyecto_activo');
    }
}
