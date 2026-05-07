<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Auditoria;

use App\Models\User;
use App\Modules\Auditoria\Infrastructure\Http\Livewire\ListadoAuditoria;
use App\Modules\Personas\Infrastructure\Persistence\Models\PersonaModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

final class AuditoriaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->markTestSkipped('TODO F35: migrar a factories tras limpieza demo seeders (ver tests/Support/EscenarioOperativo).');

    }

    public function test_observer_registra_creacion_de_persona(): void
    {
        $proyectoId = $this->proyectoCobranzaId();
        $this->app->instance('tenancy.proyecto_activo', DB::table('proyectos')->find($proyectoId));
        $this->actingAs($this->crearUsuarioConRol($proyectoId, 'SUPERVISOR'));

        $tipoCed = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');
        $persona = PersonaModel::query()->create([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoId,
            'tipo_persona' => 'fisica',
            'tipo_identificacion_id' => $tipoCed,
            'identificacion' => '8100000001',
            'nombres' => 'Audit',
            'apellidos' => 'Test',
        ]);

        $this->assertDatabaseHas('auditorias', [
            'proyecto_id' => $proyectoId,
            'entidad_tipo' => 'personas',
            'entidad_id' => $persona->id,
            'evento' => 'creado',
        ]);
    }

    public function test_observer_registra_actualizacion_con_cambios(): void
    {
        $proyectoId = $this->proyectoCobranzaId();
        $this->app->instance('tenancy.proyecto_activo', DB::table('proyectos')->find($proyectoId));
        $this->actingAs($this->crearUsuarioConRol($proyectoId, 'SUPERVISOR'));

        $tipoCed = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');
        $persona = PersonaModel::query()->create([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoId,
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
        $proyectoA = $this->proyectoCobranzaId();
        $proyectoB = $this->proyectoCxId();

        $tipoCed = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');

        // Creamos personas en ambos proyectos con diferente usuario contexto.
        $supervisorA = $this->crearUsuarioConRol($proyectoA, 'SUPERVISOR');
        $this->actingAs($supervisorA);
        $this->app->instance('tenancy.proyecto_activo', DB::table('proyectos')->find($proyectoA));
        PersonaModel::query()->create([
            'public_id' => (string) Str::ulid(), 'proyecto_id' => $proyectoA,
            'tipo_persona' => 'fisica', 'tipo_identificacion_id' => $tipoCed,
            'identificacion' => '8200000001', 'nombres' => 'A',
        ]);

        $this->app->instance('tenancy.proyecto_activo', DB::table('proyectos')->find($proyectoB));
        PersonaModel::query()->create([
            'public_id' => (string) Str::ulid(), 'proyecto_id' => $proyectoB,
            'tipo_persona' => 'fisica', 'tipo_identificacion_id' => $tipoCed,
            'identificacion' => '8200000002', 'nombres' => 'B',
        ]);

        // Vuelvo al proyecto A y consulto el Livewire.
        $this->app->instance('tenancy.proyecto_activo', DB::table('proyectos')->find($proyectoA));

        $componente = Livewire::test(ListadoAuditoria::class);
        $registros = $componente->viewData('registros');

        foreach ($registros as $r) {
            $this->assertSame((int) $r->entidad_id, DB::table('personas')
                ->where('proyecto_id', $proyectoA)
                ->where('entidad_id', '!=', 0) ? (int) $r->entidad_id : 0);
            // Chequeo directo: el proyecto del registro coincide con A
            $this->assertSame($proyectoA, (int) DB::table('auditorias')
                ->where('id', $r->id)->value('proyecto_id'));
        }
        $this->assertGreaterThan(0, $registros->total());
    }

    public function test_auditor_accede_ruta_y_gestor_recibe_403(): void
    {
        $proyectoId = $this->proyectoCobranzaId();
        $auditor = $this->crearUsuarioConRol($proyectoId, 'AUDITOR');
        $gestor = $this->crearUsuarioConRol($proyectoId, 'GESTOR');

        $this->actingAs($auditor)
            ->get(route('proyectos.auditoria', ['proyecto_id' => $proyectoId]))
            ->assertStatus(200);

        $this->actingAs($gestor)
            ->get(route('proyectos.auditoria', ['proyecto_id' => $proyectoId]))
            ->assertStatus(403);
    }

    public function test_supervisor_puede_filtrar_por_entidad(): void
    {
        $proyectoId = $this->proyectoCobranzaId();
        $this->app->instance('tenancy.proyecto_activo', DB::table('proyectos')->find($proyectoId));
        $this->actingAs($this->crearUsuarioConRol($proyectoId, 'SUPERVISOR'));

        $tipoCed = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');
        PersonaModel::query()->create([
            'public_id' => (string) Str::ulid(), 'proyecto_id' => $proyectoId,
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

    private function proyectoCobranzaId(): int
    {
        return (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
    }

    private function proyectoCxId(): int
    {
        return (int) DB::table('proyectos')->where('codigo', 'SOPORTE_DEMO_2026')->value('id');
    }

    private function crearUsuarioConRol(int $proyectoId, string $codigoRol): User
    {
        /** @var User $u */
        $u = User::query()->create([
            'name' => ucfirst(strtolower($codigoRol)),
            'email' => strtolower($codigoRol).'.'.Str::random(6).'@crm.local',
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
