<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Integracion;

use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class SanctumTokenJwtTest extends TestCase
{
    use RefreshDatabase;

    private int $proyectoId;

    private string $secret;

    protected function setUp(): void
    {
        $this->markTestSkipped('TODO F35: migrar a factories tras limpieza demo seeders (ver tests/Support/EscenarioOperativo).');

    }

    public function test_jwt_valido_emite_sanctum_token_y_jit_provisiona(): void
    {
        $jwt = $this->firmar([
            'sub' => 'wrap.s2s@wrap.io',
            'name' => 'Wrap S2S',
            'wrapper_role' => 'agent',
            'proyecto_id' => $this->proyectoId,
            'jti' => Str::uuid()->toString(),
            'iat' => time(),
            'exp' => time() + 60,
        ]);

        $response = $this->postJson('/api/integracion/sanctum-token', ['token' => $jwt]);

        $response->assertStatus(201)
            ->assertJsonStructure(['access_token', 'token_type', 'usuario_id', 'proyecto_id'])
            ->assertJson([
                'token_type' => 'Bearer',
                'proyecto_id' => $this->proyectoId,
            ]);

        $accessToken = (string) $response->json('access_token');
        $this->assertNotEmpty($accessToken);

        $usuario = User::where('email', 'wrap.s2s@wrap.io')->firstOrFail();
        $this->assertTrue((bool) $usuario->sso_provisioned);

        $rolGestorId = (int) DB::table('roles')->where('codigo', 'GESTOR')->value('id');
        $this->assertTrue(
            DB::table('usuario_proyecto_rol')
                ->where('usuario_id', $usuario->id)
                ->where('proyecto_id', $this->proyectoId)
                ->where('rol_id', $rolGestorId)
                ->exists(),
        );
    }

    public function test_token_emitido_funciona_para_preview_persona(): void
    {
        $jwt = $this->firmar([
            'sub' => 'wrap.preview@wrap.io',
            'name' => 'Wrap Preview',
            'wrapper_role' => 'agent',
            'proyecto_id' => $this->proyectoId,
            'jti' => Str::uuid()->toString(),
            'iat' => time(),
            'exp' => time() + 60,
        ]);

        $accessToken = (string) $this->postJson('/api/integracion/sanctum-token', ['token' => $jwt])
            ->assertStatus(201)
            ->json('access_token');

        $persona = DB::table('personas')->where('proyecto_id', $this->proyectoId)->first();
        $tiCodigo = (string) DB::table('tipos_identificacion')
            ->where('id', $persona->tipo_identificacion_id)->value('codigo');

        $this->withHeader('Authorization', "Bearer {$accessToken}")
            ->getJson("/api/integracion/persona?identificacion={$persona->identificacion}&tipo_identificacion_codigo={$tiCodigo}&proyecto_id={$this->proyectoId}")
            ->assertStatus(200)
            ->assertJsonStructure(['persona', 'casos']);
    }

    public function test_replay_devuelve_410(): void
    {
        $claims = [
            'sub' => 'replay.s2s@wrap.io',
            'wrapper_role' => 'agent',
            'proyecto_id' => $this->proyectoId,
            'jti' => Str::uuid()->toString(),
            'iat' => time(),
            'exp' => time() + 60,
        ];
        $jwt = $this->firmar($claims);

        $this->postJson('/api/integracion/sanctum-token', ['token' => $jwt])->assertStatus(201);
        $this->postJson('/api/integracion/sanctum-token', ['token' => $jwt])->assertStatus(410);
    }

    public function test_firma_invalida_devuelve_401(): void
    {
        $jwt = $this->firmar([
            'sub' => 'a@wrap.io',
            'proyecto_id' => $this->proyectoId,
            'jti' => Str::uuid()->toString(),
            'iat' => time(),
            'exp' => time() + 60,
        ], str_repeat('z', 64));

        $this->postJson('/api/integracion/sanctum-token', ['token' => $jwt])->assertStatus(401);
    }

    public function test_ttl_excedido_devuelve_400(): void
    {
        $jwt = $this->firmar([
            'sub' => 'a@wrap.io',
            'proyecto_id' => $this->proyectoId,
            'jti' => Str::uuid()->toString(),
            'iat' => time(),
            'exp' => time() + 600,
        ]);

        $this->postJson('/api/integracion/sanctum-token', ['token' => $jwt])->assertStatus(400);
    }

    public function test_super_admin_es_rechazado(): void
    {
        $jwt = $this->firmar([
            'sub' => 'super.s2s@wrap.io',
            'wrapper_role' => 'super_admin',
            'proyecto_id' => $this->proyectoId,
            'jti' => Str::uuid()->toString(),
            'iat' => time(),
            'exp' => time() + 60,
        ]);

        $this->postJson('/api/integracion/sanctum-token', ['token' => $jwt])->assertStatus(400);
        $this->assertNull(User::where('email', 'super.s2s@wrap.io')->first());
    }

    public function test_sin_token_devuelve_422(): void
    {
        $this->postJson('/api/integracion/sanctum-token', [])->assertStatus(422);
    }

    public function test_proyecto_inexistente_devuelve_401(): void
    {
        $jwt = $this->firmar([
            'sub' => 'a@wrap.io',
            'proyecto_id' => 999_999,
            'jti' => Str::uuid()->toString(),
            'iat' => time(),
            'exp' => time() + 60,
        ], str_repeat('q', 64));

        $this->postJson('/api/integracion/sanctum-token', ['token' => $jwt])->assertStatus(401);
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function firmar(array $claims, ?string $secret = null): string
    {
        return JWT::encode($claims, $secret ?? $this->secret, 'HS256');
    }
}
