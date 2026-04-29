<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Integracion;

use App\Models\User;
use App\Modules\Integracion\Application\DTOs\EmitirTokenSsoInput;
use App\Modules\Integracion\Application\UseCases\EmitirTokenSso;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ConsumirTokenSsoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_token_valido_con_persona_redirige_a_vista_de_trabajo(): void
    {
        $proyectoId = $this->proyectoCobranzaId();
        $usuario    = $this->crearGestorEnProyecto($proyectoId);
        $persona    = DB::table('personas')->where('proyecto_id', $proyectoId)->first();

        $tiCodigo = DB::table('tipos_identificacion')->where('id', $persona->tipo_identificacion_id)->value('codigo');

        $output = $this->emitir($usuario, $proyectoId, $persona->identificacion, (string) $tiCodigo);

        $response = $this->get($output->handshakeUrl);

        $response->assertRedirect();
        $this->assertStringContainsString("/proyectos/{$proyectoId}/trabajo/", (string) $response->headers->get('Location'));
        $this->assertAuthenticatedAs($usuario);
    }

    public function test_token_valido_solo_con_proyecto_redirige_a_bandeja(): void
    {
        $proyectoId = $this->proyectoCobranzaId();
        $usuario    = $this->crearGestorEnProyecto($proyectoId);
        $output     = $this->emitir($usuario, $proyectoId);

        $response = $this->get($output->handshakeUrl);

        $response->assertRedirect("/proyectos/{$proyectoId}/bandeja");
    }

    public function test_token_expirado_devuelve_410(): void
    {
        $usuario = $this->crearUsuario();
        $output  = $this->emitir($usuario);

        // Expirar manualmente en DB
        DB::table('integracion_tokens_sso')
            ->where('usuario_id', $usuario->id)
            ->update(['expira_en' => now()->subMinutes(10)]);

        $response = $this->get($output->handshakeUrl);
        $response->assertStatus(410);
    }

    public function test_token_ya_consumido_devuelve_410(): void
    {
        $usuario = $this->crearUsuario();
        $output  = $this->emitir($usuario);

        // Primer uso: OK
        $this->get($output->handshakeUrl);

        // Segundo uso: 410
        $response = $this->get($output->handshakeUrl);
        $response->assertStatus(410);
    }

    public function test_redirect_path_absoluto_es_rechazado(): void
    {
        $usuario = $this->crearUsuario();
        $output  = $this->emitir($usuario, null, null, null, 'https://evil.com/phishing');

        $response = $this->get($output->handshakeUrl);

        $response->assertRedirect('/proyectos');
    }

    public function test_token_invalido_devuelve_410(): void
    {
        $response = $this->get('/integracion/handshake?token=tokenquenoeexiste');
        $response->assertStatus(410);
    }

    public function test_sin_token_devuelve_410(): void
    {
        $response = $this->get('/integracion/handshake');
        $response->assertStatus(410);
    }

    private function proyectoCobranzaId(): int
    {
        return (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
    }

    private function emitir(
        User $usuario,
        ?int $proyectoId = null,
        ?string $identificacion = null,
        ?string $tiCodigo = null,
        ?string $redirectPath = null,
    ): \App\Modules\Integracion\Application\DTOs\EmitirTokenSsoOutput {
        return app(EmitirTokenSso::class)->execute(new EmitirTokenSsoInput(
            usuarioId: (int) $usuario->id,
            proyectoId: $proyectoId,
            identificacion: $identificacion,
            tipoIdentificacionCodigo: $tiCodigo,
            redirectPath: $redirectPath,
            ipOrigen: '127.0.0.1',
            userAgent: 'test',
        ));
    }

    private function crearUsuario(): User
    {
        /** @var User $u */
        $u = User::query()->create([
            'name'     => 'SSO Test',
            'email'    => 'sso.' . Str::random(6) . '@crm.local',
            'password' => Hash::make('x'),
            'activo'   => true,
        ]);

        return $u;
    }

    private function crearGestorEnProyecto(int $proyectoId): User
    {
        $usuario = $this->crearUsuario();
        $rolId   = (int) DB::table('roles')->where('codigo', 'GESTOR')->value('id');
        DB::table('usuario_proyecto_rol')->insert([
            'usuario_id' => $usuario->id,
            'proyecto_id' => $proyectoId,
            'rol_id'     => $rolId,
            'activo'     => true,
        ]);

        return $usuario;
    }
}
