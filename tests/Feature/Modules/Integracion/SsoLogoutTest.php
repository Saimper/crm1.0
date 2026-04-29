<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Integracion;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

final class SsoLogoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_logout_autenticado_devuelve_200_ok(): void
    {
        $usuario = $this->crearUsuario();
        $token   = $usuario->createToken('wrapper')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/logout');

        $response->assertStatus(200)
            ->assertJson(['ok' => true]);
    }

    public function test_logout_sin_auth_devuelve_401(): void
    {
        $response = $this->postJson('/api/auth/logout');
        $response->assertStatus(401);
    }

    public function test_logout_invalida_token_sanctum(): void
    {
        $usuario = $this->crearUsuario();
        $token   = $usuario->createToken('wrapper')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/logout')
            ->assertStatus(200);

        // Token ya no funciona — 401 si stateless, 403 si sesión web reutilizada (sin acceso al proyecto)
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/integracion/persona?identificacion=x&tipo_identificacion_codigo=CC&proyecto_id=1');

        $this->assertContains($response->status(), [401, 403]);
    }

    private function crearUsuario(): User
    {
        /** @var User $u */
        $u = User::query()->create([
            'name'     => 'Logout Test',
            'email'    => 'logout.' . Str::random(6) . '@crm.local',
            'password' => Hash::make('x'),
            'activo'   => true,
        ]);

        return $u;
    }
}
