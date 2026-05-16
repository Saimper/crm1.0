<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Integracion;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class HandshakeJwtTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    private \stdClass $mandante;

    private \stdClass $proyecto;

    private string $secret;

    private int $proyectoId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->mandante = $this->crearMandante();
        $this->proyecto = $this->crearProyectoCobranza($this->mandante);
        $this->secret = (string) $this->mandante->sso_secret;
        $this->proyectoId = (int) $this->proyecto->id;
    }

    public function test_jwt_valido_jit_provisiona_usuario_y_login(): void
    {
        $jwt = $this->firmar([
            'sub' => 'nuevo.gestor@wrapper.io',
            'name' => 'Nuevo Gestor',
            'wrapper_role' => 'agent',
            'mandante_id' => (int) $this->mandante->id,
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

    public function test_jwt_sin_proyecto_id_redirige_a_selector_filtrado_por_mandante(): void
    {
        $jwt = $this->firmar([
            'sub' => 'admin.mand@wrapper.io',
            'name' => 'Admin Mandante',
            'wrapper_role' => 'admin_tenant',
            'mandante_id' => (int) $this->mandante->id,
            'jti' => Str::uuid()->toString(),
            'iat' => time(),
            'exp' => time() + 60,
        ]);

        $this->get("/integracion/handshake?token={$jwt}")
            ->assertRedirect("/dashboard?mandante={$this->mandante->id}");

        $usuario = User::where('email', 'admin.mand@wrapper.io')->firstOrFail();

        // Sin proyecto_id, no se crea pivot.
        $this->assertSame(
            0,
            (int) DB::table('usuario_proyecto_rol')->where('usuario_id', $usuario->id)->count(),
        );
    }

    public function test_wrapper_role_admin_tenant_mapea_a_admin_mandante(): void
    {
        $jwt = $this->firmar([
            'sub' => 'admin@wrapper.io',
            'name' => 'Admin Wrapper',
            'wrapper_role' => 'admin_tenant',
            'mandante_id' => (int) $this->mandante->id,
            'proyecto_id' => $this->proyectoId,
            'jti' => Str::uuid()->toString(),
            'iat' => time(),
            'exp' => time() + 60,
        ]);

        $this->get("/integracion/handshake?token={$jwt}")->assertRedirect();

        $usuario = User::where('email', 'admin@wrapper.io')->firstOrFail();
        $rolMandanteId = (int) DB::table('roles')->where('codigo', 'ADMIN_MANDANTE')->value('id');

        // F38: rol mandante-scoped vive en usuario_mandante_rol, no en usuario_proyecto_rol.
        $this->assertTrue(
            DB::table('usuario_mandante_rol')
                ->where('usuario_id', $usuario->id)
                ->where('mandante_id', $this->mandante->id)
                ->where('rol_id', $rolMandanteId)
                ->exists()
        );
        $this->assertSame(0, DB::table('usuario_proyecto_rol')->where('usuario_id', $usuario->id)->count());
    }

    public function test_wrapper_role_super_admin_es_rechazado(): void
    {
        $jwt = $this->firmar([
            'sub' => 'super@wrapper.io',
            'name' => 'Super Wrapper',
            'wrapper_role' => 'super_admin',
            'mandante_id' => (int) $this->mandante->id,
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
            'mandante_id' => (int) $this->mandante->id,
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
            'mandante_id' => (int) $this->mandante->id,
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
            'mandante_id' => (int) $this->mandante->id,
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
            'mandante_id' => (int) $this->mandante->id,
            'proyecto_id' => $this->proyectoId,
            'jti' => Str::uuid()->toString(),
            'iat' => time(),
            'exp' => time() + 60,
        ];
        $jwt = $this->firmar($claims);

        $this->get("/integracion/handshake?token={$jwt}")->assertRedirect();
        $this->get("/integracion/handshake?token={$jwt}")->assertStatus(410);
    }

    public function test_mandante_inexistente_devuelve_401(): void
    {
        $jwt = $this->firmar([
            'sub' => 'a@wrapper.io',
            'mandante_id' => 999_999,
            'proyecto_id' => $this->proyectoId,
            'jti' => Str::uuid()->toString(),
            'iat' => time(),
            'exp' => time() + 60,
        ], str_repeat('y', 64));

        $this->get("/integracion/handshake?token={$jwt}")->assertStatus(401);
    }

    public function test_mandante_sin_sso_secret_devuelve_404(): void
    {
        DB::table('mandantes')->where('id', $this->mandante->id)->update(['sso_secret' => null]);

        $jwt = $this->firmar([
            'sub' => 'a@wrapper.io',
            'mandante_id' => (int) $this->mandante->id,
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
            'mandante_id' => (int) $this->mandante->id,
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
            'mandante_id' => (int) $this->mandante->id,
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
        $persona = $this->crearPersonaEn($this->proyecto, '12345678');
        $tiCodigo = (string) DB::table('tipos_identificacion')
            ->where('id', $persona->tipo_identificacion_id)->value('codigo');

        $jwt = $this->firmar([
            'sub' => 'persona@wrapper.io',
            'name' => 'Persona Wrap',
            'wrapper_role' => 'agent',
            'mandante_id' => (int) $this->mandante->id,
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
            'mandante_id' => (int) $this->mandante->id,
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

    public function test_email_se_normaliza_lowercase_y_trim(): void
    {
        $jwt = $this->firmar([
            'sub' => '  MIXED.case@WRAPPER.io  ',
            'name' => 'Mixed Case',
            'wrapper_role' => 'agent',
            'mandante_id' => (int) $this->mandante->id,
            'proyecto_id' => $this->proyectoId,
            'jti' => Str::uuid()->toString(),
            'iat' => time(),
            'exp' => time() + 60,
        ]);

        $this->get("/integracion/handshake?token={$jwt}")->assertRedirect();

        $this->assertNotNull(User::where('email', 'mixed.case@wrapper.io')->first());
    }

    public function test_iss_valido_se_acepta(): void
    {
        $jwt = $this->firmar([
            'iss' => "wrapper:{$this->mandante->id}",
            'aud' => 'crm',
            'sub' => 'iss.valid@wrapper.io',
            'wrapper_role' => 'agent',
            'mandante_id' => (int) $this->mandante->id,
            'proyecto_id' => $this->proyectoId,
            'jti' => Str::uuid()->toString(),
            'iat' => time(),
            'exp' => time() + 60,
        ]);

        $this->get("/integracion/handshake?token={$jwt}")->assertRedirect();
    }

    public function test_iss_mal_formado_devuelve_400(): void
    {
        $jwt = $this->firmar([
            'iss' => 'wrapper:999',
            'sub' => 'iss.bad@wrapper.io',
            'mandante_id' => (int) $this->mandante->id,
            'proyecto_id' => $this->proyectoId,
            'jti' => Str::uuid()->toString(),
            'iat' => time(),
            'exp' => time() + 60,
        ]);

        $this->get("/integracion/handshake?token={$jwt}")->assertStatus(400);
    }

    public function test_aud_distinto_de_crm_devuelve_400(): void
    {
        $jwt = $this->firmar([
            'aud' => 'no-crm',
            'sub' => 'aud.bad@wrapper.io',
            'mandante_id' => (int) $this->mandante->id,
            'proyecto_id' => $this->proyectoId,
            'jti' => Str::uuid()->toString(),
            'iat' => time(),
            'exp' => time() + 60,
        ]);

        $this->get("/integracion/handshake?token={$jwt}")->assertStatus(400);
    }

    public function test_secret_old_vigente_acepta_firma(): void
    {
        $secretViejo = (string) $this->mandante->sso_secret;
        $secretNuevo = bin2hex(random_bytes(32));

        DB::table('mandantes')
            ->where('id', $this->mandante->id)
            ->update([
                'sso_secret' => $secretNuevo,
                'sso_secret_old' => $secretViejo,
                'sso_secret_old_expires_at' => now()->addHours(24),
            ]);

        $jwt = $this->firmar([
            'sub' => 'old.secret@wrapper.io',
            'wrapper_role' => 'agent',
            'mandante_id' => (int) $this->mandante->id,
            'proyecto_id' => $this->proyectoId,
            'jti' => Str::uuid()->toString(),
            'iat' => time(),
            'exp' => time() + 60,
        ], $secretViejo);

        $this->get("/integracion/handshake?token={$jwt}")->assertRedirect();
    }

    public function test_secret_old_expirado_rechaza_firma(): void
    {
        $secretViejo = (string) $this->mandante->sso_secret;
        $secretNuevo = bin2hex(random_bytes(32));

        DB::table('mandantes')
            ->where('id', $this->mandante->id)
            ->update([
                'sso_secret' => $secretNuevo,
                'sso_secret_old' => $secretViejo,
                'sso_secret_old_expires_at' => now()->subHour(),
            ]);

        $jwt = $this->firmar([
            'sub' => 'old.expired@wrapper.io',
            'mandante_id' => (int) $this->mandante->id,
            'proyecto_id' => $this->proyectoId,
            'jti' => Str::uuid()->toString(),
            'iat' => time(),
            'exp' => time() + 60,
        ], $secretViejo);

        $this->get("/integracion/handshake?token={$jwt}")->assertStatus(401);
    }

    public function test_sin_token_devuelve_400(): void
    {
        $this->get('/integracion/handshake')->assertStatus(400);
    }

    public function test_token_mal_formado_devuelve_400(): void
    {
        $this->get('/integracion/handshake?token=no.es.jwt')->assertStatus(400);
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function firmar(array $claims, ?string $secret = null): string
    {
        return JWT::encode($claims, $secret ?? $this->secret, 'HS256');
    }
}
