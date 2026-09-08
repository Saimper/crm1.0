<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Auditoria;

use App\Modules\Auditoria\Infrastructure\Http\Livewire\ListadoAuditoria;
use App\Modules\Personas\Infrastructure\Persistence\Models\PersonaModel;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class AuditoriaTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_observer_registra_creacion_de_persona(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearSupervisor($proyecto));

        $tipoCed = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');
        $persona = PersonaModel::query()->create([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'tipo_persona' => 'fisica',
            'tipo_identificacion_id' => $tipoCed,
            'identificacion' => '8100000001',
            'nombres' => 'Audit',
            'apellidos' => 'Test',
        ]);

        $this->assertDatabaseHas('auditorias', [
            'proyecto_id' => $proyecto->id,
            'entidad_tipo' => 'personas',
            'entidad_id' => $persona->id,
            'evento' => 'creado',
        ]);
    }

    public function test_observer_registra_actualizacion_con_cambios(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearSupervisor($proyecto));

        $tipoCed = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');
        $persona = PersonaModel::query()->create([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'tipo_persona' => 'fisica',
            'tipo_identificacion_id' => $tipoCed,
            'identificacion' => '8100000002',
            'nombres' => 'Antes',
            'apellidos' => 'Original',
        ]);

        $persona->nombres = 'Despues';
        $persona->save();

        $registro = DB::table('auditorias')
            ->where('entidad_tipo', 'personas')
            ->where('entidad_id', $persona->id)
            ->where('evento', 'actualizado')
            ->first();

        $this->assertNotNull($registro);
        $cambios = json_decode((string) $registro->cambios, true);
        $this->assertArrayHasKey('nombres', $cambios);
        $this->assertSame('Antes', $cambios['nombres']['antes']);
        $this->assertSame('Despues', $cambios['nombres']['despues']);
    }

    public function test_listado_no_muestra_eventos_de_otro_proyecto(): void
    {
        $proyectoA = $this->crearProyectoCobranza();
        $proyectoB = $this->crearProyectoCx();

        $tipoCed = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');

        // Creamos personas en ambos proyectos con diferente usuario contexto.
        $supervisorA = $this->crearSupervisor($proyectoA);
        $this->actingAs($supervisorA);
        $this->activarProyecto($proyectoA);
        PersonaModel::query()->create([
            'public_id' => (string) Str::ulid(), 'proyecto_id' => $proyectoA->id,
            'tipo_persona' => 'fisica', 'tipo_identificacion_id' => $tipoCed,
            'identificacion' => '8200000001', 'nombres' => 'A',
        ]);

        $this->activarProyecto($proyectoB);
        PersonaModel::query()->create([
            'public_id' => (string) Str::ulid(), 'proyecto_id' => $proyectoB->id,
            'tipo_persona' => 'fisica', 'tipo_identificacion_id' => $tipoCed,
            'identificacion' => '8200000002', 'nombres' => 'B',
        ]);

        // Vuelvo al proyecto A y consulto el Livewire.
        $this->activarProyecto($proyectoA);

        $componente = Livewire::test(ListadoAuditoria::class);
        $registros = $componente->viewData('registros');

        foreach ($registros as $r) {
            // Chequeo directo: el proyecto del registro coincide con A.
            $this->assertSame((int) $proyectoA->id, (int) DB::table('auditorias')
                ->where('id', $r->id)->value('proyecto_id'));
        }
        $this->assertGreaterThan(0, $registros->total());

        // Y el evento del proyecto B existe, pero no salió en el listado.
        $idsListados = collect($registros->items())->pluck('id')->map(fn ($v): int => (int) $v)->all();
        $eventoB = (int) DB::table('auditorias')
            ->where('proyecto_id', $proyectoB->id)
            ->where('entidad_tipo', 'personas')
            ->value('id');
        $this->assertGreaterThan(0, $eventoB);
        $this->assertNotContains($eventoB, $idsListados);
    }

    public function test_auditor_accede_ruta_y_gestor_recibe_403(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $auditor = $this->crearAuditor($proyecto);
        $gestor = $this->crearGestor($proyecto);

        $this->actingAs($auditor)
            ->get(route('proyectos.auditoria', ['proyecto_id' => $proyecto->id]))
            ->assertStatus(200);

        $this->actingAs($gestor)
            ->get(route('proyectos.auditoria', ['proyecto_id' => $proyecto->id]))
            ->assertStatus(403);
    }

    public function test_supervisor_puede_filtrar_por_entidad(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearSupervisor($proyecto));

        $tipoCed = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');
        PersonaModel::query()->create([
            'public_id' => (string) Str::ulid(), 'proyecto_id' => $proyecto->id,
            'tipo_persona' => 'fisica', 'tipo_identificacion_id' => $tipoCed,
            'identificacion' => '8300000001', 'nombres' => 'Filtrada',
        ]);

        $componente = Livewire::test(ListadoAuditoria::class)
            ->set('entidadTipo', 'personas');

        $registros = $componente->viewData('registros');
        foreach ($registros as $r) {
            $this->assertSame('personas', $r->entidad_tipo);
        }
        $this->assertGreaterThan(0, $registros->total());
    }
}
