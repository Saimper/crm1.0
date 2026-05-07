<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Integracion;

use Database\Seeders\DatabaseSeeder;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class MultiTenancyJwtTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_jwt_firmado_con_secret_de_proyecto_b_no_vale_para_proyecto_a(): void
    {
        $proyectoA = $this->crearProyectoCobranza();
        $proyectoB = $this->crearProyectoCx();

        $jwt = JWT::encode([
            'sub' => 'cross.tenant@wrap.io',
            'wrapper_role' => 'agent',
            'proyecto_id' => (int) $proyectoA->id,
            'jti' => Str::uuid()->toString(),
            'iat' => time(),
            'exp' => time() + 60,
        ], (string) $proyectoB->sso_secret, 'HS256');

        $this->get("/integracion/handshake?token={$jwt}")->assertStatus(401);
        $this->postJson('/api/integracion/sanctum-token', ['token' => $jwt])->assertStatus(401);
    }

    public function test_jti_consumido_se_aisla_por_proyecto_en_la_tabla(): void
    {
        $proyectoA = $this->crearProyectoCobranza();
        $proyectoB = $this->crearProyectoCx();

        $jwtA = JWT::encode([
            'sub' => 'tenant.a@wrap.io',
            'wrapper_role' => 'agent',
            'proyecto_id' => (int) $proyectoA->id,
            'jti' => Str::uuid()->toString(),
            'iat' => time(),
            'exp' => time() + 60,
        ], (string) $proyectoA->sso_secret, 'HS256');

        $this->get("/integracion/handshake?token={$jwtA}")->assertRedirect();

        $consumidos = DB::table('sso_tokens_consumidos')->get();
        $this->assertCount(1, $consumidos);
        $this->assertSame((int) $proyectoA->id, (int) $consumidos[0]->proyecto_id);
        $this->assertNotSame((int) $proyectoB->id, (int) $consumidos[0]->proyecto_id);
    }
}
