<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Integracion;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PreviewPersonaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->markTestSkipped('TODO F35: migrar a factories tras limpieza demo seeders (ver tests/Support/EscenarioOperativo).');

    }

    public function test_con_sanctum_auth_y_persona_existente_devuelve_200_con_json(): void
    {
        $proyectoId = $this->proyectoCobranzaId();
        $usuario = $this->crearGestorEnProyecto($proyectoId);
        $persona = DB::table('personas')->where('proyecto_id', $proyectoId)->first();
        $tiCodigo = DB::table('tipos_identificacion')->where('id', $persona->tipo_identificacion_id)->value('codigo');

        $response = $this->actingAs($usuario, 'sanctum')
            ->getJson("/api/integracion/persona?identificacion={$persona->identificacion}&tipo_identificacion_codigo={$tiCodigo}&proyecto_id={$proyectoId}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'persona' => ['public_id', 'nombre', 'identificacion', 'tipo_identificacion'],
                'casos',
                'compromiso_vigente',
                'ultima_gestion',
            ]);
    }

    public function test_sin_auth_devuelve_401(): void
    {
        $response = $this->getJson('/api/integracion/persona?identificacion=123&tipo_identificacion_codigo=CC&proyecto_id=1');
        $response->assertStatus(401);
    }

    public function test_persona_en_otro_proyecto_devuelve_404(): void
    {
        $proyectoA = $this->proyectoCobranzaId();
        $proyectoB = $this->proyectoServicioId();
        $usuario = $this->crearGestorEnProyecto($proyectoA);

        // Persona del proyecto A buscada con proyecto_id del proyecto B
        $persona = DB::table('personas')->where('proyecto_id', $proyectoA)->first();
        $tiCodigo = DB::table('tipos_identificacion')->where('id', $persona->tipo_identificacion_id)->value('codigo');

        $response = $this->actingAs($usuario, 'sanctum')
            ->getJson("/api/integracion/persona?identificacion={$persona->identificacion}&tipo_identificacion_codigo={$tiCodigo}&proyecto_id={$proyectoB}");

        // El usuario no tiene acceso al proyecto B, entonces 403
        $response->assertStatus(403);
    }

    public function test_usuario_sin_rol_en_proyecto_devuelve_403(): void
    {
        $proyectoId = $this->proyectoCobranzaId();
        $persona = DB::table('personas')->where('proyecto_id', $proyectoId)->first();
        $tiCodigo = DB::table('tipos_identificacion')->where('id', $persona->tipo_identificacion_id)->value('codigo');

        // Usuario sin rol en ningún proyecto
        /** @var User $sinRol */
        $sinRol = User::query()->create([
            'name' => 'Sin Rol',
            'email' => 'sinrol.'.Str::random(6).'@crm.local',
            'password' => Hash::make('x'),
            'activo' => true,
        ]);

        $response = $this->actingAs($sinRol, 'sanctum')
            ->getJson("/api/integracion/persona?identificacion={$persona->identificacion}&tipo_identificacion_codigo={$tiCodigo}&proyecto_id={$proyectoId}");

        $response->assertStatus(403);
    }

    public function test_persona_inexistente_devuelve_404(): void
    {
        $proyectoId = $this->proyectoCobranzaId();
        $usuario = $this->crearGestorEnProyecto($proyectoId);

        $response = $this->actingAs($usuario, 'sanctum')
            ->getJson("/api/integracion/persona?identificacion=99999999NOEXISTE&tipo_identificacion_codigo=CC&proyecto_id={$proyectoId}");

        $response->assertStatus(404);
    }

    private function proyectoCobranzaId(): int
    {
        return (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
    }

    private function proyectoServicioId(): int
    {
        return (int) DB::table('proyectos')->where('codigo', 'SERVICIO_DEMO_2026')->value('id');
    }

    private function crearGestorEnProyecto(int $proyectoId): User
    {
        /** @var User $u */
        $u = User::query()->create([
            'name' => 'Gestor Preview',
            'email' => 'prev.'.Str::random(6).'@crm.local',
            'password' => Hash::make('x'),
            'activo' => true,
        ]);
        $rolId = (int) DB::table('roles')->where('codigo', 'GESTOR')->value('id');
        DB::table('usuario_proyecto_rol')->insert([
            'usuario_id' => $u->id, 'proyecto_id' => $proyectoId,
            'rol_id' => $rolId, 'activo' => true,
        ]);

        return $u;
    }
}
