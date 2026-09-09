<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response
            ->assertOk()
            ->assertSeeVolt('pages.auth.login');
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'password')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->get('/dashboard')->assertOk();
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $component = Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'wrong-password');

        $component->call('login');

        $component
            ->assertHasErrors()
            ->assertNoRedirect();

        $this->assertGuest();
    }

    public function test_navigation_menu_can_be_rendered(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertSeeVolt('layout.navigation');
    }

    public function test_root_does_not_send_an_operator_to_administration(): void
    {
        $this->get('/')->assertRedirect('/dashboard');
    }

    public function test_admin_login_keeps_the_administrative_destination(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = $this->crearAdminGlobal();

        Volt::test('pages.auth.login')
            ->set('form.email', $admin->email)
            ->set('form.password', 'x')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect('/admin');
    }

    public function test_login_preserves_an_intended_operational_destination(): void
    {
        $user = User::factory()->create();
        session(['url.intended' => '/profile']);

        Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'password')
            ->call('login')
            ->assertRedirect('/profile');
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Volt::test('layout.navigation');

        $component->call('logout');

        $component
            ->assertHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
    }
}
