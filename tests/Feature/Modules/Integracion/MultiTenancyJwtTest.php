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

    public function test_jwt_firmado_con_secret_de_mandante_b_no_vale_para_mandante_a(): void
    {
        $mandanteA = $this->crearMandante();
        $mandanteB = $this->crearMandante();
        $proyectoA = $this->crearProyectoCobranza($mandanteA);

        $jwt = JWT::encode([
            'sub' => 'cross.tenant@wrap.io',
            'wrapper_role' => 'agent',
            'mandante_id' => (int) $mandanteA->id,
            'proyecto_id' => (int) $proyectoA->id,
            'jti' => Str::uuid()->toString(),
            'iat' => time(),
            'exp' => time() + 60,
        ], (string) $mandanteB->sso_secret, 'HS256');

        $this->get("/integracion/handshake?token={$jwt}")->assertStatus(401);
        $this->postJson('/api/integracion/sanctum-token', ['token' => $jwt])->assertStatus(401);
    }

    public function test_proyecto_de_otro_mandante_devuelve_403(): void
    {
        $mandanteA = $this->crearMandante();
        $mandanteB = $this->crearMandante();
        $proyectoB = $this->crearProyectoCobranza($mandanteB);

        $jwt = JWT::encode([
            'sub' => 'attacker@wrap.io',
            'wrapper_role' => 'agent',
            'mandante_id' => (int) $mandanteA->id,
            'proyecto_id' => (int) $proyectoB->id,
            'jti' => Str::uuid()->toString(),
            'iat' => time(),
            'exp' => time() + 60,
        ], (string) $mandanteA->sso_secret, 'HS256');

        $this->get("/integracion/handshake?token={$jwt}")->assertStatus(403);
    }

    public function test_jti_consumido_se_aisla_por_mandante_en_la_tabla(): void
    {
        $mandanteA = $this->crearMandante();
        $proyectoA = $this->crearProyectoCobranza($mandanteA);

        $jwtA = JWT::encode([
            'sub' => 'tenant.a@wrap.io',
            'wrapper_role' => 'agent',
            'mandante_id' => (int) $mandanteA->id,
            'proyecto_id' => (int) $proyectoA->id,
            'jti' => Str::uuid()->toString(),
            'iat' => time(),
            'exp' => time() + 60,
        ], (string) $mandanteA->sso_secret, 'HS256');

        $this->get("/integracion/handshake?token={$jwtA}")->assertRedirect();

        $consumidos = DB::table('sso_tokens_consumidos')->get();
        $this->assertCount(1, $consumidos);
        $this->assertSame((int) $mandanteA->id, (int) $consumidos[0]->mandante_id);
        $this->assertSame((int) $proyectoA->id, (int) $consumidos[0]->proyecto_id);
    }
}
