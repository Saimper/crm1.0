<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Importaciones;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ExportsAdicionalesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_export_casos_devuelve_csv_con_cabeceras_y_datos(): void
    {
        $proyectoId = $this->proyectoCobranzaId();
        $supervisor = $this->crearSupervisorEn($proyectoId);

        $response = $this->actingAs($supervisor)
            ->get(route('proyectos.importaciones.exportar-casos', ['proyecto_id' => $proyectoId]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $contenido = $response->streamedContent();
        $this->assertStringContainsString('caso_public_id', $contenido);
        $this->assertStringContainsString('cobranza', $contenido);
    }

    public function test_export_gestiones_devuelve_csv(): void
    {
        $proyectoId = $this->proyectoCobranzaId();
        $supervisor = $this->crearSupervisorEn($proyectoId);

        $response = $this->actingAs($supervisor)
            ->get(route('proyectos.importaciones.exportar-gestiones', ['proyecto_id' => $proyectoId]));

        $response->assertStatus(200)->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('gestion_public_id', $response->streamedContent());
    }

    public function test_export_compromisos_devuelve_csv(): void
    {
        $proyectoId = $this->proyectoCobranzaId();
        $supervisor = $this->crearSupervisorEn($proyectoId);

        $response = $this->actingAs($supervisor)
            ->get(route('proyectos.importaciones.exportar-compromisos', ['proyecto_id' => $proyectoId]));

        $response->assertStatus(200)->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('compromiso_public_id', $response->streamedContent());
    }

    public function test_gestor_sin_permiso_recibe_403_en_exports(): void
    {
        $proyectoId = $this->proyectoCobranzaId();
        $gestor = $this->crearConRol($proyectoId, 'GESTOR');

        $this->actingAs($gestor)
            ->get(route('proyectos.importaciones.exportar-casos', ['proyecto_id' => $proyectoId]))
            ->assertStatus(403);
    }

    private function proyectoCobranzaId(): int
    {
        return (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
    }

    private function crearSupervisorEn(int $proyectoId): User
    {
        return $this->crearConRol($proyectoId, 'SUPERVISOR');
    }

    private function crearConRol(int $proyectoId, string $codigoRol): User
    {
        /** @var User $u */
        $u = User::query()->create([
            'name' => ucfirst(strtolower($codigoRol)), 'email' => strtolower($codigoRol).'.'.Str::random(6).'@crm.local',
            'password' => Hash::make('x'), 'activo' => true,
        ]);
        $rolId = (int) DB::table('roles')->where('codigo', $codigoRol)->value('id');
        DB::table('usuario_proyecto_rol')->insert([
            'usuario_id' => $u->id, 'proyecto_id' => $proyectoId, 'rol_id' => $rolId, 'activo' => true,
        ]);

        return $u;
    }
}
