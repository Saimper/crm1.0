<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Auditoria;

use App\Modules\Personas\Infrastructure\Persistence\Models\PersonaModel;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class ExportarAuditoriaTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_auditor_descarga_csv(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);
        $auditor = $this->crearAuditor($proyecto);
        $supervisor = $this->crearSupervisor($proyecto);

        $this->actingAs($supervisor);
        $tipoCed = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');
        PersonaModel::query()->create([
            'public_id' => (string) Str::ulid(), 'proyecto_id' => $proyecto->id,
            'tipo_persona' => 'fisica', 'tipo_identificacion_id' => $tipoCed,
            'identificacion' => '7100000001', 'nombres' => 'ExportCsv', 'apellidos' => 'Test',
        ]);

        $this->actingAs($auditor);
        $response = $this->get(route('proyectos.auditoria.exportar', ['proyecto_id' => $proyecto->id]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();
        $this->assertStringContainsString('public_id', $csv);
        $this->assertStringContainsString('entidad_tipo', $csv);
        $this->assertStringContainsString('personas', $csv);
        $this->assertStringContainsString('creado', $csv);
    }

    public function test_gestor_403_en_exportacion(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $gestor = $this->crearGestor($proyecto);

        $this->actingAs($gestor)
            ->get(route('proyectos.auditoria.exportar', ['proyecto_id' => $proyecto->id]))
            ->assertStatus(403);
    }

    public function test_filtros_por_entidad_y_evento(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);
        $supervisor = $this->crearSupervisor($proyecto);

        $this->actingAs($supervisor);
        $tipoCed = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');
        $p = PersonaModel::query()->create([
            'public_id' => (string) Str::ulid(), 'proyecto_id' => $proyecto->id,
            'tipo_persona' => 'fisica', 'tipo_identificacion_id' => $tipoCed,
            'identificacion' => '7200000001', 'nombres' => 'Filtro', 'apellidos' => 'Test',
        ]);
        $p->nombres = 'FiltroModificado';
        $p->save();

        $auditor = $this->crearAuditor($proyecto);
        $this->actingAs($auditor);

        // Solo actualizaciones
        $response = $this->get(route('proyectos.auditoria.exportar', [
            'proyecto_id' => $proyecto->id,
            'entidad_tipo' => 'personas',
            'evento' => 'actualizado',
        ]));
        $response->assertStatus(200);
        $csv = $response->streamedContent();

        $this->assertStringContainsString('actualizado', $csv);
        $this->assertStringNotContainsString(',creado,', $csv);
    }

    public function test_export_no_muestra_auditorias_de_otro_proyecto(): void
    {
        $proyectoA = $this->crearProyectoCobranza();
        $proyectoB = $this->crearProyectoCx();

        $tipoCed = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');

        $supervisorA = $this->crearSupervisor($proyectoA);
        $this->actingAs($supervisorA);

        $this->activarProyecto($proyectoB);
        PersonaModel::query()->create([
            'public_id' => (string) Str::ulid(), 'proyecto_id' => $proyectoB->id,
            'tipo_persona' => 'fisica', 'tipo_identificacion_id' => $tipoCed,
            'identificacion' => '7300000999', 'nombres' => 'SoloB',
        ]);

        // Vuelvo a A y descargo. Quien descarga es un AUDITOR y no el supervisor
        // que escribió: exportar exige `auditoria.exportar`, permiso que el
        // seeder le niega al SUPERVISOR (ver ExportarAuditoriaController). Lo que
        // el test comprueba sigue siendo el recorte por proyecto, no el permiso.
        $auditorA = $this->crearAuditor($proyectoA);
        $this->actingAs($auditorA);
        $this->activarProyecto($proyectoA);
        $response = $this->get(route('proyectos.auditoria.exportar', ['proyecto_id' => $proyectoA->id]));

        $response->assertStatus(200);
        $this->assertStringNotContainsString('7300000999', $response->streamedContent());
    }
}
