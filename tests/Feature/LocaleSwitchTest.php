<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class LocaleSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_renders_in_spanish_for_es_user(): void
    {
        $user = User::factory()->create(['locale' => 'es']);

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertSee('Idioma de la interfaz', false)
            ->assertSee('Cerrar sesión', false);
    }

    public function test_profile_renders_in_english_for_en_user(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertSee('Interface language', false)
            ->assertSee('Sign out', false);
    }

    public function test_language_switch_persists_user_locale(): void
    {
        $user = User::factory()->create(['locale' => 'es']);
        $this->actingAs($user);

        Volt::test('profile.update-language-form')
            ->call('setLocale', 'en');

        $this->assertSame('en', $user->fresh()->locale);
    }

    public function test_language_switch_rejects_unsupported_locale(): void
    {
        $user = User::factory()->create(['locale' => 'es']);
        $this->actingAs($user);

        Volt::test('profile.update-language-form')
            ->call('setLocale', 'fr')
            ->assertHasErrors('locale');

        $this->assertSame('es', $user->fresh()->locale);
    }
}
