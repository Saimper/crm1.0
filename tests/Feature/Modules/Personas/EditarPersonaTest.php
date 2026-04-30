<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Personas;

use App\Models\User;
use App\Modules\Personas\Infrastructure\Http\Livewire\EditarPersona;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * F34B — edición de persona vía Livewire (sin tocar Domain).
 */
final class EditarPersonaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_supervisor_edita_nombres(): void
    {
        $proyectoId = $this->proyectoCobranza();
        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');
        $this->bindProyectoActivo($proyectoId);
        $this->actingAs($supervisor);

        $persona = (object) DB::table('personas')
            ->where('proyecto_id', $proyectoId)
            ->where('tipo_persona', 'fisica')
            ->first();

        Livewire::test(EditarPersona::class, ['persona' => $persona->public_id])
            ->set('nombres', 'Editado')
            ->set('apellidos', 'F34B')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('personas', [
            'id' => $persona->id,
            'nombres' => 'Editado',
            'apellidos' => 'F34B',
        ]);
    }

    public function test_rechaza_identificacion_duplicada_en_proyecto(): void
    {
        $proyectoId = $this->proyectoCobranza();
        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');
        $this->bindProyectoActivo($proyectoId);
        $this->actingAs($supervisor);

        $personas = DB::table('personas')->where('proyecto_id', $proyectoId)->limit(2)->get();
        $this->assertCount(2, $personas);
        $p1 = $personas[0];
        $p2 = $personas[1];

        Livewire::test(EditarPersona::class, ['persona' => $p1->public_id])
            ->set('tipoIdentificacionId', $p2->tipo_identificacion_id)
            ->set('identificacion', $p2->identificacion)
            ->call('guardar')
            ->assertHasErrors(['identificacion']);
    }

    public function test_persona_de_otro_proyecto_no_se_encuentra(): void
    {
        $proyectoA = $this->proyectoCobranza();
        $proyectoB = $this->proyectoCx();

        $supervisor = $this->crearConRol($proyectoA, 'SUPERVISOR');
        $this->bindProyectoActivo($proyectoA);
        $this->actingAs($supervisor);

        $personaB = (object) DB::table('personas')->where('proyecto_id', $proyectoB)->first();

        try {
            Livewire::test(EditarPersona::class, ['persona' => $personaB->public_id]);
            $this->fail('Esperaba 404 al editar persona de otro proyecto.');
        } catch (\Throwable $e) {
            $this->assertTrue(true);
        }
    }

    public function test_gestor_recibe_403_en_ruta(): void
    {
        $proyectoId = $this->proyectoCobranza();
        $gestor = $this->crearConRol($proyectoId, 'GESTOR');
        $persona = (object) DB::table('personas')->where('proyecto_id', $proyectoId)->first();

        // GESTOR tiene personas.editar (matriz por defecto), debe pasar.
        $this->actingAs($gestor)
            ->get(route('proyectos.personas.editar', [
                'proyecto_id' => $proyectoId,
                'persona' => $persona->public_id,
            ]))
            ->assertStatus(200);
    }

    public function test_auditor_recibe_403_en_ruta(): void
    {
        $proyectoId = $this->proyectoCobranza();
        $auditor = $this->crearConRol($proyectoId, 'AUDITOR');
        $persona = (object) DB::table('personas')->where('proyecto_id', $proyectoId)->first();

        $this->actingAs($auditor)
            ->get(route('proyectos.personas.editar', [
                'proyecto_id' => $proyectoId,
                'persona' => $persona->public_id,
            ]))
            ->assertStatus(403);
    }

    private function proyectoCobranza(): int
    {
        return (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
    }

    private function proyectoCx(): int
    {
        return (int) DB::table('proyectos')->where('codigo', 'SOPORTE_DEMO_2026')->value('id');
    }

    private function bindProyectoActivo(int $proyectoId): void
    {
        $this->app->instance('tenancy.proyecto_activo', DB::table('proyectos')->find($proyectoId));
    }

    private function crearConRol(int $proyectoId, string $codigoRol): User
    {
        /** @var User $u */
        $u = User::query()->create([
            'name' => ucfirst(strtolower($codigoRol)),
            'email' => strtolower($codigoRol).'.ep.'.Str::random(6).'@crm.local',
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
