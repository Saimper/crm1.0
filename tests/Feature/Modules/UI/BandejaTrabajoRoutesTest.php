<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\UI;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class BandejaTrabajoRoutesTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_gestor_accede_a_bandeja_del_proyecto(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $gestor = $this->crearGestor($proyecto);

        $this->actingAs($gestor)
            ->get("/proyectos/{$proyecto->id}/bandeja")
            ->assertOk()
            ->assertSee('Bandeja');
    }

    public function test_usuario_sin_permiso_bandeja_recibe_403(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $auditor = $this->crearAuditor($proyecto);

        // AUDITOR no tiene asignaciones.ver_propia según matriz.
        $this->actingAs($auditor)
            ->get("/proyectos/{$proyecto->id}/bandeja")
            ->assertForbidden();
    }

    public function test_gestor_accede_a_vista_de_trabajo_de_persona_en_proyecto(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $gestor = $this->crearGestor($proyecto);

        $persona = $this->crearPersonaEn($proyecto);

        $this->actingAs($gestor)
            ->get("/proyectos/{$proyecto->id}/trabajo/{$persona->public_id}")
            ->assertOk()
            ->assertSee('Vista de trabajo');
    }

    public function test_vista_de_trabajo_rechaza_persona_de_otro_proyecto(): void
    {
        $mandante = $this->crearMandante();
        $proyecto = $this->crearProyectoCobranza($mandante);
        $gestor = $this->crearGestor($proyecto);

        // Otro proyecto del mismo mandante, con una persona propia.
        $otroProyecto = $this->crearProyectoCobranza($mandante);
        $personaAjena = $this->crearPersonaEn($otroProyecto);

        $this->actingAs($gestor)
            ->get("/proyectos/{$proyecto->id}/trabajo/{$personaAjena->public_id}")
            ->assertNotFound();
    }

    public function test_bandeja_de_otro_proyecto_sin_acceso_recibe_403(): void
    {
        $mandante = $this->crearMandante();
        $proyectoAsignado = $this->crearProyectoCobranza($mandante);
        $gestor = $this->crearGestor($proyectoAsignado);

        $otroProyecto = $this->crearProyectoCobranza($mandante);

        $this->actingAs($gestor)
            ->get("/proyectos/{$otroProyecto->id}/bandeja")
            ->assertForbidden();
    }
}
