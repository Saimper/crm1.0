<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Integracion;

use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

final class HandshakeJwtTest extends TestCase
{
    use RefreshDatabase;

    private int $proyectoId;

    private string $secret;

    protected function setUp(): void
    {
        $this->markTestSkipped('TODO F35: migrar a factories tras limpieza demo seeders (ver tests/Support/EscenarioOperativo).');

    }

    public function test_jwt_valido_jit_provisiona_usuario_y_login(): void
    {
        $jwt = $this->firmar([
            'sub' => 'nuevo.gestor@wrapper.io',
            'name' => 'Nuevo Gestor',
            'wrapper_role' => 'agent',
            'proyecto_id' => $this->proyectoId,
            'jti' => Str::uuid()->toString(),
            'iat' => time(),
            'exp' => time() + 60,
        ]);

        $response = $this->get("/integracion/handshake?token={$jwt}");
        $response->assertRedirect("/proyectos/{$this->proyectoId}/bandeja");

        $usuario = User::where('email', 'nuevo.gestor@wrapper.io')->first();
        $this->assertNotNull($usuario);
        $this->assertSame('Nuevo Gestor', $usuario->name);
        $this->assertTrue((bool) $usuario->sso_provisioned);
        $this->assertNotNull($usuario->ultimo_sso_en);
        $this->assertAuthenticatedAs($usuario);

        $rolGestorId = (int) DB::table('roles')->where('codigo', 'GESTOR')->value('id');
        $this->assertTrue(
            DB::table('usuario_proyecto_rol')
                ->where('usuario_id', $usuario->id)
                ->where('proyecto_id', $this->proyectoId)
                ->where('rol_id', $rolGestorId)
                ->where('activo', true)
                ->exists(),
            'Pivot usuario_proyecto_rol debe existir con rol GESTOR.'
        );
    }

    public function test_wrapper_role_tenant_admin_mapea_a_supervisor(): void
    {
        $jwt = $this->firmar([
            'sub' => 'admin@wrapper.io',
            'name' => 'Admin Wrapper',
            'wrapper_role' => 'tenant_admin',
            'proyecto_id' => $this->proyectoId,
            'jti' => Str::uuid()->toString(),
            'iat' => time(),
            'exp' => time() + 60,
        ]);

        $this->get("/integracion/handshake?token={$jwt}")->assertRedirect();

        $usuario = User::where('email', 'admin@wrapper.io')->firstOrFail();
        $rolSupId = (int) DB::table('roles')->where('codigo', 'SUPERVISOR')->value('id');

        $this->assertTrue(
            DB::table('usuario_proyecto_rol')
                ->where('usuario_id', $usuario->id)
                ->where('proyecto_id', $this->proyectoId)
                ->where('rol_id', $rolSupId)
                ->exists()
        );
    }

    public function test_wrapper_role_super_admin_es_rechazado(): void
    {
        $jwt = $this->firmar([
            'sub' => 'super@wrapper.io',
            'name' => 'Super Wrapper',
            'wrapper_role' => 'super_admin',
            'proyecto_id' => $this->proyectoId,
            'jti' => Str::uuid()->toString(),
            'iat' => time(),
            'exp' => time() + 60,
        ]);

        $this->get("/integracion/handshake?token={$jwt}")->assertStatus(400);
        $this->assertNull(User::where('email', 'super@wrapper.io')->first());
    }

    public function test_firma_invalida_devuelve_401(): void
    {
        $jwt = $this->firmar([
            'sub' => 'a@wrapper.io',
            'proyecto_id' => $this->proyectoId,
            'jti' => Str::uuid()->toString(),
            'iat' => time(),
            'exp' => time() + 60,
        ], str_repeat('z', 64));

        $this->get("/integracion/handshake?token={$jwt}")->assertStatus(401);
    }

    public function test_token_expirado_devuelve_401(): void
    {
        $jwt = $this->firmar([
            'sub' => 'a@wrapper.io',
            'proyecto_id' => $this->proyectoId,
            'jti' => Str::uuid()->toString(),
            'iat' => time() - 600,
            'exp' => time() - 60,
        ]);

        $this->get("/integracion/handshake?token={$jwt}")->assertStatus(401);
    }

    public function test_ttl_excedido_devuelve_400(): void
    {
        $jwt = $this->firmar([
            'sub' => 'a@wrapper.io',
            'proyecto_id' => $this->proyectoId,
            'jti' => Str::uuid()->toString(),
            'iat' => time(),
            'exp' => time() + 600,
        ]);

        $this->get("/integracion/handshake?token={$jwt}")->assertStatus(400);
    }

    public function test_jti_replay_devuelve_410(): void
    {
        $claims = [
            'sub' => 'replay@wrapper.io',
            'name' => 'Replay',
            'wrapper_role' => 'agent',
            'proyecto_id' => $this->proyectoId,
            'jti' => Str::uuid()->toString(),
            'iat' => time(),
            'exp' => time() + 60,
        ];
        $jwt = $this->firmar($claims);

        $this->get("/integracion/handshake?token={$jwt}")->assertRedirect();
        $this->get("/integracion/handshake?token={$jwt}")->assertStatus(410);
    }

    public function test_proyecto_inexistente_devuelve_401(): void
    {
        $jwt = $this->firmar([
            'sub' => 'a@wrapper.io',
            'proyecto_id' => 999_999,
            'jti' => Str::uuid()->toString(),
            'iat' => time(),
            'exp' => time() + 60,
        ], str_repeat('y', 64));

        $this->get("/integracion/handshake?token={$jwt}")->assertStatus(401);
    }

    public function test_proyecto_sin_sso_secret_devuelve_404(): void
    {
        DB::table('proyectos')->where('id', $this->proyectoId)->update(['sso_secret' => null]);

        $jwt = $this->firmar([
            'sub' => 'a@wrapper.io',
            'proyecto_id' => $this->proyectoId,
            'jti' => Str::uuid()->toString(),
            'iat' => time(),
            'exp' => time() + 60,
        ], str_repeat('w', 64));

        $this->get("/integracion/handshake?token={$jwt}")->assertStatus(404);
    }

    public function test_redirect_path_relativo_se_respeta(): void
    {
        $jwt = $this->firmar([
            'sub' => 'b@wrapper.io',
            'name' => 'B',
            'wrapper_role' => 'agent',
            'proyecto_id' => $this->proyectoId,
            'redirect_path' => "/proyectos/{$this->proyectoId}/reportes/operativos",
            'jti' => Str::uuid()->toString(),
            'iat' => time(),
            'exp' => time() + 60,
        ]);

        $this->get("/integracion/handshake?token={$jwt}")
            ->assertRedirect("/proyectos/{$this->proyectoId}/reportes/operativos");
    }

    public function test_redirect_path_absoluto_es_rechazado_y_cae_a_bandeja(): void
    {
        $jwt = $this->firmar([
            'sub' => 'c@wrapper.io',
            'name' => 'C',
            'wrapper_role' => 'agent',
            'proyecto_id' => $this->proyectoId,
            'redirect_path' => 'https://evil.example.com/phish',
            'jti' => Str::uuid()->toString(),
            'iat' => time(),
            'exp' => time() + 60,
        ]);

        $this->get("/integracion/handshake?token={$jwt}")
            ->assertRedirect("/proyectos/{$this->proyectoId}/bandeja");
    }

    public function test_persona_match_redirige_a_vista_de_trabajo(): void
    {
        $persona = DB::table('personas')->where('proyecto_id', $this->proyectoId)->first();
        $tiCodigo = (string) DB::table('tipos_identificacion')
            ->where('id', $persona->tipo_identificacion_id)->value('codigo');

        $jwt = $this->firmar([
            'sub' => 'persona@wrapper.io',
            'name' => 'Persona Wrap',
            'wrapper_role' => 'agent',
            'proyecto_id' => $this->proyectoId,
            'identificacion' => $persona->identificacion,
            'tipo_identificacion_codigo' => $tiCodigo,
            'jti' => Str::uuid()->toString(),
            'iat' => time(),
            'exp' => time() + 60,
        ]);

        $response = $this->get("/integracion/handshake?token={$jwt}");
        $response->assertRedirect();
        $this->assertStringContainsString(
            "/proyectos/{$this->proyectoId}/trabajo/",
            (string) $response->headers->get('Location'),
        );
    }

    public function test_usuario_existente_no_duplica_pivot(): void
    {
        $usuario = User::query()->create([
            'name' => 'Ya Existo',
            'email' => 'existo@wrapper.io',
            'password' => Hash::make('x'),
            'activo' => true,
        ]);
        $rolGestorId = (int) DB::table('roles')->where('codigo', 'GESTOR')->value('id');
        DB::table('usuario_proyecto_rol')->insert([
            'usuario_id' => $usuario->id,
            'proyecto_id' => $this->proyectoId,
            'rol_id' => $rolGestorId,
            'activo' => true,
        ]);

        $jwt = $this->firmar([
            'sub' => 'existo@wrapper.io',
            'name' => 'Ya Existo',
            'wrapper_role' => 'agent',
            'proyecto_id' => $this->proyectoId,
            'jti' => Str::uuid()->toString(),
            'iat' => time(),
            'exp' => time() + 60,
        ]);

        $this->get("/integracion/handshake?token={$jwt}")->assertRedirect();

        $count = DB::table('usuario_proyecto_rol')
            ->where('usuario_id', $usuario->id)
            ->where('proyecto_id', $this->proyectoId)
            ->count();
        $this->assertSame(1, (int) $count, 'No debe duplicar pivot al re-loguear.');
    }

    public function test_usuario_existente_con_rol_admin_no_se_degrada_por_wrapper_role_agent(): void
    {
        $usuario = User::query()->create([
            'name' => 'Sup Existente',
            'email' => 'sup@wrapper.io',
            'password' => Hash::make('x'),
            'activo' => true,
        ]);
        $rolSupId = (int) DB::table('roles')->where('codigo', 'SUPERVISOR')->value('id');
        DB::table('usuario_proyecto_rol')->insert([
            'usuario_id' => $usuario->id,
            'proyecto_id' => $this->proyectoId,
            'rol_id' => $rolSupId,
            'activo' => true,
        ]);

        $jwt = $this->firmar([
            'sub' => 'sup@wrapper.io',
            'name' => 'Sup Existente',
            'wrapper_role' => 'agent',
            'proyecto_id' => $this->proyectoId,
            'jti' => Str::uuid()->toString(),
            'iat' => time(),
            'exp' => time() + 60,
        ]);

        $this->get("/integracion/handshake?token={$jwt}")->assertRedirect();

        $rolPivot = (int) DB::table('usuario_proyecto_rol')
            ->where('usuario_id', $usuario->id)
            ->where('proyecto_id', $this->proyectoId)
            ->value('rol_id');
        $this->assertSame($rolSupId, $rolPivot, 'No debe degradar rol SUPERVISOR a GESTOR.');
    }

    public function test_usuario_existente_sincroniza_nombre_desde_wrapper(): void
    {
        $usuario = User::query()->create([
            'name' => 'Nombre Viejo',
            'email' => 'sync@wrapper.io',
            'password' => Hash::make('x'),
            'activo' => true,
        ]);

        $jwt = $this->firmar([
            'sub' => 'sync@wrapper.io',
            'name' => 'Nombre Nuevo',
            'wrapper_role' => 'agent',
            'proyecto_id' => $this->proyectoId,
            'jti' => Str::uuid()->toString(),
            'iat' => time(),
            'exp' => time() + 60,
        ]);

        $this->get("/integracion/handshake?token={$jwt}")->assertRedirect();

        $this->assertSame('Nombre Nuevo', (string) $usuario->fresh()->name);
    }

    public function test_sin_token_devuelve_400(): void
    {
        $this->get('/integracion/handshake')->assertStatus(400);
    }

    public function test_token_mal_formado_devuelve_400(): void
    {
        $this->get('/integracion/handshake?token=no.es.jwt')->assertStatus(400);
    }

    public function test_proyecto_id_del_jwt_no_coincide_con_secret_devuelve_401(): void
    {
        $proyectoB = (int) DB::table('proyectos')->where('codigo', 'SOPORTE_DEMO_2026')->value('id');
        $secretB = (string) DB::table('proyectos')->where('id', $proyectoB)->value('sso_secret');

        // JWT firmado con secret de B pero claim proyecto_id apunta a A.
        // Bypass: el extractor inseguro lee proyecto_id=A, busca secret de A,
        // intenta verificar firma (que fue hecha con secret de B) → JwtFirmaInvalida.
        $jwt = $this->firmar([
            'sub' => 'cross@wrapper.io',
            'proyecto_id' => $this->proyectoId,
            'jti' => Str::uuid()->toString(),
            'iat' => time(),
            'exp' => time() + 60,
        ], $secretB);

        $this->get("/integracion/handshake?token={$jwt}")->assertStatus(401);
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function firmar(array $claims, ?string $secret = null): string
    {
        return JWT::encode($claims, $secret ?? $this->secret, 'HS256');
    }
}
