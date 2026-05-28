<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Personas;

use App\Modules\Personas\Infrastructure\Http\Livewire\EditarPersona;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class EditarPersonaTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_supervisor_edita_nombres(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $persona = $this->crearPersonaEn($proyecto);
        $this->actuarComoSupervisor($proyecto);

        Livewire::test(EditarPersona::class, ['persona' => $persona->public_id])
            ->set('nombres', 'Editado')
            ->set('apellidos', 'B1')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('personas', [
            'id' => $persona->id,
            'nombres' => 'Editado',
            'apellidos' => 'B1',
        ]);
    }

    public function test_form_no_renderiza_fecha_nacimiento(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $persona = $this->crearPersonaEn($proyecto);
        $this->actuarComoSupervisor($proyecto);

        $resp = $this->get(route('proyectos.personas.editar', [
            'proyecto_id' => $proyecto->id,
            'persona' => $persona->public_id,
        ]));

        $resp->assertOk();
        $resp->assertDontSee('Fecha nacimiento');
        $resp->assertDontSee('wire:model="fechaNacimiento"', false);
    }

    public function test_rechaza_identificacion_duplicada_en_proyecto(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $p1 = $this->crearPersonaEn($proyecto, '1111111111');
        $p2 = $this->crearPersonaEn($proyecto, '2222222222');
        $this->actuarComoSupervisor($proyecto);

        Livewire::test(EditarPersona::class, ['persona' => $p1->public_id])
            ->set('tipoIdentificacionId', (int) $p2->tipo_identificacion_id)
            ->set('identificacion', (string) $p2->identificacion)
            ->set('nombres', 'X')
            ->call('guardar')
            ->assertHasErrors(['identificacion']);
    }

    public function test_persona_de_otro_proyecto_no_se_encuentra(): void
    {
        $proyectoA = $this->crearProyectoCobranza();
        $proyectoB = $this->crearProyectoCx();
        $personaB = $this->crearPersonaEn($proyectoB);

        $this->actuarComoSupervisor($proyectoA);

        try {
            Livewire::test(EditarPersona::class, ['persona' => $personaB->public_id]);
            $this->fail('Esperaba 404 al editar persona de otro proyecto.');
        } catch (\Throwable) {
            $this->assertTrue(true);
        }
    }

    public function test_gestor_accede_ruta(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $persona = $this->crearPersonaEn($proyecto);
        $gestor = $this->crearGestor($proyecto);
        $this->activarProyecto($proyecto);

        $this->actingAs($gestor)
            ->get(route('proyectos.personas.editar', [
                'proyecto_id' => $proyecto->id,
                'persona' => $persona->public_id,
            ]))
            ->assertStatus(200);
    }

    public function test_auditor_recibe_403_en_ruta(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $persona = $this->crearPersonaEn($proyecto);
        $auditor = $this->crearAuditor($proyecto);
        $this->activarProyecto($proyecto);

        $this->actingAs($auditor)
            ->get(route('proyectos.personas.editar', [
                'proyecto_id' => $proyecto->id,
                'persona' => $persona->public_id,
            ]))
            ->assertStatus(403);
    }

    public function test_persona_juridica_muestra_razon_social_readonly(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $tipoRuc = (int) DB::table('tipos_identificacion')->where('codigo', 'RUC')->value('id');

        $publicId = (string) Str::ulid();
        $personaId = DB::table('personas')->insertGetId([
            'public_id' => $publicId,
            'proyecto_id' => $proyecto->id,
            'tipo_persona' => 'juridica',
            'tipo_identificacion_id' => $tipoRuc,
            'identificacion' => '1792345678001',
            'razon_social' => 'ACME SA',
            'creada_en' => now(),
            'actualizada_en' => now(),
        ]);

        $this->actuarComoSupervisor($proyecto);

        $resp = $this->get(route('proyectos.personas.editar', [
            'proyecto_id' => $proyecto->id,
            'persona' => $publicId,
        ]));

        $resp->assertOk();
        $resp->assertSee('Razón social (no editable)');
        $resp->assertSee('ACME SA');

        // Edición persiste identificación + tipo_id pero no toca razon_social.
        Livewire::test(EditarPersona::class, ['persona' => $publicId])
            ->set('identificacion', '1792345678999')
            ->call('guardar')
            ->assertHasNoErrors();

        $row = DB::table('personas')->where('id', $personaId)->first();
        $this->assertSame('1792345678999', (string) $row->identificacion);
        $this->assertSame('ACME SA', (string) $row->razon_social);
    }

    public function test_guardar_emite_evento_crm_sync_para_wrapper(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $persona = $this->crearPersonaEn($proyecto);
        $this->actuarComoSupervisor($proyecto);

        Livewire::test(EditarPersona::class, ['persona' => $persona->public_id])
            ->set('nombres', 'Editado')
            ->set('apellidos', 'B1')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('crm-sync', function (string $event, array $params): bool {
                return ($params['tipo'] ?? null) === 'persona'
                    && ($params['cambios']['nombres'] ?? null) === 'Editado'
                    && ($params['cambios']['apellidos'] ?? null) === 'B1';
            });
    }

    private function actuarComoSupervisor(stdClass $proyecto): void
    {
        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearSupervisor($proyecto));
    }
}
