<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Integracion;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    public function test_identificacion_sin_tipo_redirige_a_vista_de_trabajo(): void
    {
        $persona = $this->crearPersonaEn($this->proyecto, '87654321');

        $jwt = $this->firmar([
            'sub' => 'persona.solo.id@wrapper.io',
            'name' => 'Persona Solo ID',
            'wrapper_role' => 'agent',
            'mandante_id' => (int) $this->mandante->id,
            'proyecto_id' => $this->proyectoId,
            'identificacion' => $persona->identificacion,
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

    public function test_numero_prestamo_redirige_a_trabajo_con_caso(): void
    {
        $persona = $this->crearPersonaEn($this->proyecto);
        $cartera = $this->crearCarteraEn($this->proyecto);
        $estado = $this->crearEstadoCasoEn($this->proyecto);

        $casoPublicId = (string) Str::ulid();
        $numeroPrestamo = 'LOAN-'.Str::random(8);

        $casoId = (int) DB::table('casos')->insertGetId([
            'public_id' => $casoPublicId,
            'proyecto_id' => $this->proyectoId,
            'cartera_id' => $cartera->id,
            'persona_id' => $persona->id,
            'tipo_caso' => 'cobranza',
            'estado_caso_id' => $estado->id,
            'fecha_ingreso' => Carbon::today(),
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        DB::table('casos_cobranza')->insert([
            'caso_id' => $casoId,
            'proyecto_id' => $this->proyectoId,
            'numero_prestamo' => $numeroPrestamo,
            'moneda' => 'USD',
            'monto_original' => 1000.00,
            'saldo_capital' => 1000.00,
            'saldo_total' => 1000.00,
            'cuota_mensual' => 100.00,
            'cuotas_totales' => 12,
            'fecha_desembolso' => Carbon::today()->subYear(),
            'fecha_vencimiento' => Carbon::today()->addYear(),
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        $jwt = $this->firmar([
            'sub' => 'agente.prestamo@wrapper.io',
            'name' => 'Agente Prestamo',
            'wrapper_role' => 'agent',
            'mandante_id' => (int) $this->mandante->id,
            'proyecto_id' => $this->proyectoId,
            'numero_prestamo' => $numeroPrestamo,
            'jti' => Str::uuid()->toString(),
            'iat' => time(),
            'exp' => time() + 60,
        ]);

        $response = $this->get("/integracion/handshake?token={$jwt}");
        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString(
            "/proyectos/{$this->proyectoId}/trabajo/{$persona->public_id}/{$casoPublicId}",
            $location,
        );
    }

    public function test_numero_prestamo_inexistente_cae_a_bandeja(): void
    {
        $jwt = $this->firmar([
            'sub' => 'agente.prestamo.no@wrapper.io',
            'name' => 'Agente Sin Prestamo',
            'wrapper_role' => 'agent',
            'mandante_id' => (int) $this->mandante->id,
            'proyecto_id' => $this->proyectoId,
            'numero_prestamo' => 'NO-EXISTE-'.Str::random(8),
            'jti' => Str::uuid()->toString(),
            'iat' => time(),
            'exp' => time() + 60,
        ]);

        $this->get("/integracion/handshake?token={$jwt}")
            ->assertRedirect("/proyectos/{$this->proyectoId}/bandeja");
    }

    public function test_archived_portfolio_never_opens_a_work_view_or_suggests_duplicate_identity(): void
    {
        $person = $this->crearPersonaEn($this->proyecto, 'ARCHIVED-IDENTITY');
        $portfolio = $this->crearCarteraEn($this->proyecto);
        $case = $this->crearCasoEn($this->proyecto, ['persona' => $person, 'cartera' => $portfolio]);
        DB::table('casos_cobranza')->insert(['proyecto_id' => $this->proyectoId, 'caso_id' => $case, 'numero_prestamo' => 'ARCHIVED-LOAN', 'moneda' => 'USD']);

        foreach ([['activo' => false, 'eliminada_en' => null], ['activo' => true, 'eliminada_en' => now()]] as $archive) {
            DB::table('carteras')->where('id', $portfolio->id)->update($archive);
            foreach ([['numero_prestamo' => 'ARCHIVED-LOAN'], ['identificacion' => $person->identificacion], ['numero_prestamo' => 'ARCHIVED-LOAN', 'identificacion' => $person->identificacion]] as $claims) {
                $this->get('/integracion/handshake?token='.$this->firmarParaAgente($claims))
                    ->assertRedirect("/proyectos/{$this->proyectoId}/bandeja")
                    ->assertSessionMissing('crm_persona_public_id');
            }
        }
        $this->assertDatabaseHas('personas', ['id' => $person->id, 'eliminada_en' => null]);
    }

    public function test_archived_loan_falls_back_to_same_person_with_an_active_debt(): void
    {
        $person = $this->crearPersonaEn($this->proyecto, 'MIXED-IDENTITY');
        $archived = $this->crearCarteraEn($this->proyecto);
        $active = $this->crearCarteraEn($this->proyecto);
        foreach ([$archived->id => 'RETIRED-LOAN', $active->id => 'CURRENT-LOAN'] as $portfolioId => $reference) {
            $case = $this->crearCasoEn($this->proyecto, ['persona' => $person, 'cartera' => $portfolioId === $archived->id ? $archived : $active]);
            DB::table('casos_cobranza')->insert(['proyecto_id' => $this->proyectoId, 'caso_id' => $case, 'numero_prestamo' => $reference, 'moneda' => 'USD']);
        }
        DB::table('carteras')->where('id', $archived->id)->update(['activo' => false]);

        $path = "/proyectos/{$this->proyectoId}/trabajo/{$person->public_id}";
        $this->get('/integracion/handshake?token='.$this->firmarParaAgente(['numero_prestamo' => 'RETIRED-LOAN', 'identificacion' => $person->identificacion]))
            ->assertRedirect($path)->assertSessionHas('crm_persona_public_id', $person->public_id);
        $this->get($path)->assertOk()->assertSee('CURRENT-LOAN')->assertDontSee('RETIRED-LOAN');
    }

    public function test_loan_match_cannot_anchor_an_archived_person(): void
    {
        $person = $this->crearPersonaEn($this->proyecto, 'RETIRED-PERSON');
        $case = $this->crearCasoEn($this->proyecto, ['persona' => $person]);
        DB::table('casos_cobranza')->insert(['proyecto_id' => $this->proyectoId, 'caso_id' => $case, 'numero_prestamo' => 'RETIRED-PERSON-LOAN', 'moneda' => 'USD']);
        DB::table('personas')->where('id', $person->id)->update(['eliminada_en' => now()]);

        $this->get('/integracion/handshake?token='.$this->firmarParaAgente(['numero_prestamo' => 'RETIRED-PERSON-LOAN']))
            ->assertRedirect("/proyectos/{$this->proyectoId}/bandeja")->assertSessionMissing('crm_persona_public_id');
    }

    public function test_screen_pop_respects_account_permission_scope_and_preserves_active_identity_fallback(): void
    {
        $person = $this->crearPersonaEn($this->proyecto, 'SCOPED-IDENTITY');
        $allowed = $this->crearCarteraEn($this->proyecto);
        $denied = $this->crearCarteraEn($this->proyecto);
        $user = $this->crearGestor($this->proyecto);
        DB::table('usuario_proyecto_rol_cartera')->insert(['usuario_id' => $user->id, 'proyecto_id' => $this->proyectoId,
            'rol_id' => DB::table('roles')->where('codigo', 'GESTOR')->value('id'), 'cartera_id' => $allowed->id]);
        $case = $this->crearCasoEn($this->proyecto, ['persona' => $person, 'cartera' => $denied]);
        DB::table('casos_cobranza')->insert(['proyecto_id' => $this->proyectoId, 'caso_id' => $case, 'numero_prestamo' => 'DENIED-LOAN', 'moneda' => 'USD']);
        $claims = ['sub' => $user->email, 'numero_prestamo' => 'DENIED-LOAN', 'identificacion' => $person->identificacion];
        $this->get('/integracion/handshake?token='.$this->firmarParaAgente($claims))
            ->assertRedirect("/proyectos/{$this->proyectoId}/bandeja")->assertSessionMissing('crm_persona_public_id');

        $this->crearCasoEn($this->proyecto, ['persona' => $person, 'cartera' => $allowed]);
        $this->get('/integracion/handshake?token='.$this->firmarParaAgente($claims))
            ->assertRedirect("/proyectos/{$this->proyectoId}/trabajo/{$person->public_id}")
            ->assertSessionHas('crm_persona_public_id', $person->public_id);
    }

    public function test_identificacion_no_encontrada_cae_a_bandeja_con_aviso(): void
    {
        $jwt = $this->firmarParaAgente([
            'identificacion' => '99999999',
            'tipo_identificacion_codigo' => 'CED',
        ]);

        $this->get("/integracion/handshake?token={$jwt}")
            ->assertRedirect("/proyectos/{$this->proyectoId}/bandeja?sin_persona=99999999&tipo=CED")
            ->assertSessionMissing('crm_persona_public_id');
    }

    public function test_identificacion_con_espacios_se_recorta_antes_de_buscar(): void
    {
        $persona = $this->crearPersonaEn($this->proyecto, '55566677');

        $jwt = $this->firmarParaAgente(['identificacion' => '  55566677 ']);

        $this->get("/integracion/handshake?token={$jwt}")
            ->assertRedirect("/proyectos/{$this->proyectoId}/trabajo/{$persona->public_id}")
            ->assertSessionHas('crm_persona_public_id', $persona->public_id);
    }

    public function test_identificacion_repetida_en_dos_tipos_no_abre_ninguna_ficha(): void
    {
        $this->crearPersonaEn($this->proyecto, '44455566');
        $this->crearPersonaConTipo($this->proyecto, '44455566', 'PAS');

        $jwt = $this->firmarParaAgente(['identificacion' => '44455566']);

        $this->get("/integracion/handshake?token={$jwt}")
            ->assertRedirect("/proyectos/{$this->proyectoId}/bandeja?sin_persona=44455566&ambigua=1")
            ->assertSessionMissing('crm_persona_public_id');
    }

    public function test_identificacion_repetida_se_desambigua_con_el_tipo(): void
    {
        $this->crearPersonaEn($this->proyecto, '44455566');
        $pasaporte = $this->crearPersonaConTipo($this->proyecto, '44455566', 'PAS');

        $jwt = $this->firmarParaAgente([
            'identificacion' => '44455566',
            'tipo_identificacion_codigo' => 'PAS',
        ]);

        $this->get("/integracion/handshake?token={$jwt}")
            ->assertRedirect("/proyectos/{$this->proyectoId}/trabajo/{$pasaporte->public_id}");
    }

    public function test_numero_prestamo_inexistente_cae_a_la_identificacion(): void
    {
        $persona = $this->crearPersonaEn($this->proyecto, '66677788');

        $jwt = $this->firmarParaAgente([
            'numero_prestamo' => 'NO-EXISTE-'.Str::random(8),
            'identificacion' => '66677788',
        ]);

        $this->get("/integracion/handshake?token={$jwt}")
            ->assertRedirect("/proyectos/{$this->proyectoId}/trabajo/{$persona->public_id}");
    }

    public function test_persona_anclada_se_limpia_en_un_handshake_sin_ficha(): void
    {
        $persona = $this->crearPersonaEn($this->proyecto, '77788899');

        $this->get('/integracion/handshake?token='.$this->firmarParaAgente(['identificacion' => '77788899']))
            ->assertSessionHas('crm_persona_public_id', $persona->public_id);

        $this->get('/integracion/handshake?token='.$this->firmarParaAgente())
            ->assertRedirect("/proyectos/{$this->proyectoId}/bandeja")
            ->assertSessionMissing('crm_persona_public_id');
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function firmarParaAgente(array $extra = []): string
    {
        return $this->firmar(array_merge([
            'sub' => 'agente.pop@wrapper.io',
            'name' => 'Agente Pop',
            'wrapper_role' => 'agent',
            'mandante_id' => (int) $this->mandante->id,
            'proyecto_id' => $this->proyectoId,
            'jti' => Str::uuid()->toString(),
            'iat' => time(),
            'exp' => time() + 60,
        ], $extra));
    }

    private function crearPersonaConTipo(\stdClass $proyecto, string $identificacion, string $tipoCodigo): \stdClass
    {
        $tipoId = (int) DB::table('tipos_identificacion')->where('codigo', $tipoCodigo)->value('id');

        $id = DB::table('personas')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'tipo_persona' => 'fisica',
            'tipo_identificacion_id' => $tipoId,
            'identificacion' => $identificacion,
            'nombres' => 'Homónima',
            'apellidos' => 'Con Pasaporte',
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        return DB::table('personas')->find($id);
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function firmar(array $claims, ?string $secret = null): string
    {
        return JWT::encode($claims, $secret ?? $this->secret, 'HS256');
    }
}
