<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Integracion;

use Database\Seeders\DatabaseSeeder;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * F35 — multi-tenancy del flow JWT: un JWT firmado con el secret del proyecto
 * B no puede consumirse para autenticarse contra el proyecto A. La firma se
 * verifica siempre contra el proyecto declarado en el claim, así que un secret
 * mal coordinado falla con 401.
 */
final class MultiTenancyJwtTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_jwt_firmado_con_secret_de_proyecto_b_no_vale_para_proyecto_a(): void
    {
        $proyectoA = DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->first();
        $proyectoB = DB::table('proyectos')->where('codigo', 'SOPORTE_DEMO_2026')->first();

        // Firmamos con el secret de B pero le decimos al CRM que es proyecto A.
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
        $proyectoA = DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->first();
        $proyectoB = DB::table('proyectos')->where('codigo', 'SOPORTE_DEMO_2026')->first();

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
