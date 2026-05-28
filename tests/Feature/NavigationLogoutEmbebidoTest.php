<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * El CRM embebido en el iframe del wrapper (handshake SSO con parent_origin)
 * no gestiona su propia sesión: el botón de logout va deshabilitado y el
 * método ignora cualquier intento de cierre.
 */
final class NavigationLogoutEmbebidoTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_y_perfil_deshabilitados_cuando_esta_embebido(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        session(['crm_parent_origin' => 'https://wrapper.example']);

        Volt::test('layout.navigation')
            ->assertSee('disabled', false)
            ->assertDontSeeHtml('href="'.route('profile').'"')
            ->call('logout');

        $this->assertTrue(Auth::check());
    }

    public function test_logout_y_perfil_activos_cuando_no_esta_embebido(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Volt::test('layout.navigation')
            ->assertSeeHtml('href="'.route('profile').'"')
            ->call('logout');

        $this->assertFalse(Auth::check());
    }
}
