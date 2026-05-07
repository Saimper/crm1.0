<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Auditoria;

use App\Models\User;
use App\Modules\Personas\Infrastructure\Persistence\Models\PersonaModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ExportarAuditoriaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->markTestSkipped('TODO F35: migrar a factories tras limpieza demo seeders (ver tests/Support/EscenarioOperativo).');

    }

    public function test_auditor_descarga_csv(): void
    {
        $proyectoId = $this->proyectoId();
        $this->bindProyectoActivo($proyectoId);
        $auditor = $this->crearConRol($proyectoId, 'AUDITOR');
        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');

        $this->actingAs($supervisor);
        $tipoCed = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');
        PersonaModel::query()->create([
            'public_id' => (string) Str::ulid(), 'proyecto_id' => $proyectoId,
            'tipo_persona' => 'fisica', 'tipo_identificacion_id' => $tipoCed,
            'identificacion' => '7100000001', 'nombres' => 'ExportCsv', 'apellidos' => 'Test',
        ]);

        $this->actingAs($auditor);
        $response = $this->get(route('proyectos.auditoria.exportar', ['proyecto_id' => $proyectoId]));

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
        $proyectoId = $this->proyectoId();
        $gestor = $this->crearConRol($proyectoId, 'GESTOR');

        $this->actingAs($gestor)
            ->get(route('proyectos.auditoria.exportar', ['proyecto_id' => $proyectoId]))
            ->assertStatus(403);
    }

    public function test_filtros_por_entidad_y_evento(): void
    {
        $proyectoId = $this->proyectoId();
        $this->bindProyectoActivo($proyectoId);
        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');

        $this->actingAs($supervisor);
        $tipoCed = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');
        $p = PersonaModel::query()->create([
            'public_id' => (string) Str::ulid(), 'proyecto_id' => $proyectoId,
            'tipo_persona' => 'fisica', 'tipo_identificacion_id' => $tipoCed,
            'identificacion' => '7200000001', 'nombres' => 'Filtro', 'apellidos' => 'Test',
        ]);
        $p->nombres = 'FiltroModificado';
        $p->save();

        $auditor = $this->crearConRol($proyectoId, 'AUDITOR');
        $this->actingAs($auditor);

        // Solo actualizaciones
        $response = $this->get(route('proyectos.auditoria.exportar', [
            'proyecto_id' => $proyectoId,
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
        $proyectoA = $this->proyectoId();
        $proyectoB = (int) DB::table('proyectos')->where('codigo', 'SOPORTE_DEMO_2026')->value('id');

        $tipoCed = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');

        $supervisorA = $this->crearConRol($proyectoA, 'SUPERVISOR');
        $this->actingAs($supervisorA);

        $this->bindProyectoActivo($proyectoB);
        PersonaModel::query()->create([
            'public_id' => (string) Str::ulid(), 'proyecto_id' => $proyectoB,
            'tipo_persona' => 'fisica', 'tipo_identificacion_id' => $tipoCed,
            'identificacion' => '7300000999', 'nombres' => 'SoloB',
        ]);

        // Vuelvo a A y descargo
        $this->bindProyectoActivo($proyectoA);
        $response = $this->get(route('proyectos.auditoria.exportar', ['proyecto_id' => $proyectoA]));

        $response->assertStatus(200);
        $this->assertStringNotContainsString('7300000999', $response->streamedContent());
    }

    private function proyectoId(): int
    {
        return (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
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
