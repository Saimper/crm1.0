<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Integracion;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class SsoHandshakeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_credenciales_validas_sin_proyecto_devuelve_handshake_url(): void
    {
        $usuario = $this->crearUsuarioActivo('gestor.sso@crm.local', 'secret123');

        $response = $this->postJson('/api/auth/sso-handshake', [
            'email' => 'gestor.sso@crm.local',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['handshake_url', 'expira_en']);

        $this->assertStringContainsString('/integracion/handshake?token=', (string) $response->json('handshake_url'));
        $this->assertNotNull($response->json('expira_en'));
    }

    public function test_credenciales_validas_con_proyecto_id_incluido_en_url(): void
    {
        $proyectoId = $this->proyectoCobranzaId();
        $this->crearUsuarioActivo('gestor.proy@crm.local', 'secret123');

        $response = $this->postJson('/api/auth/sso-handshake', [
            'email' => 'gestor.proy@crm.local',
            'password' => 'secret123',
            'proyecto_id' => $proyectoId,
        ]);

        $response->assertStatus(200);
        $this->assertStringContainsString('/integracion/handshake?token=', (string) $response->json('handshake_url'));

        $tokenHash = $this->extraerHashDesdeUrl((string) $response->json('handshake_url'));
        $fila = DB::table('integracion_tokens_sso')->where('proyecto_id', $proyectoId)->first();
        $this->assertNotNull($fila);
    }

    public function test_credenciales_invalidas_devuelven_401(): void
    {
        $this->crearUsuarioActivo('test.401@crm.local', 'correctpass');

        $response = $this->postJson('/api/auth/sso-handshake', [
            'email' => 'test.401@crm.local',
            'password' => 'wrongpass',
        ]);

        $response->assertStatus(401);
        $this->assertArrayNotHasKey('handshake_url', (array) $response->json());
    }

    public function test_email_inexistente_devuelve_401_sin_filtrar(): void
    {
        $response = $this->postJson('/api/auth/sso-handshake', [
            'email' => 'noexiste@crm.local',
            'password' => 'cualquiercosa',
        ]);

        $response->assertStatus(401);
    }

    public function test_throttle_11_intentos_devuelve_429(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/auth/sso-handshake', [
                'email' => "x{$i}@x.com",
                'password' => 'y',
            ]);
        }

        $response = $this->postJson('/api/auth/sso-handshake', [
            'email' => 'x11@x.com',
            'password' => 'y',
        ]);

        $response->assertStatus(429);
    }

    private function crearUsuarioActivo(string $email, string $password): User
    {
        /** @var User $u */
        $u = User::query()->create([
            'name' => 'Test SSO',
            'email' => $email,
            'password' => Hash::make($password),
            'activo' => true,
        ]);

        return $u;
    }

    private function proyectoCobranzaId(): int
    {
        return (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
    }

    private function extraerHashDesdeUrl(string $url): string
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $params);

        return (string) ($params['token'] ?? '');
    }
}
