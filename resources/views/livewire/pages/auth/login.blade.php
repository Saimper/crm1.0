<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: route('admin.dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <h1 style="font-size:18px;font-weight:600;color:var(--text);margin-bottom:4px;">Iniciar sesión</h1>
    <p style="font-size:13px;color:var(--text-secondary);margin-bottom:20px;">Ingresa tus credenciales para acceder.</p>

    <x-auth-session-status :status="session('status')" />

    <form wire:submit="login" style="display:flex;flex-direction:column;gap:14px;">
        <x-ui.form-field label="Email" :error="$errors->first('form.email')">
            <input wire:model="form.email" id="email" type="email" name="email" required autofocus
                   autocomplete="username" class="input">
        </x-ui.form-field>

        <x-ui.form-field label="Contraseña" :error="$errors->first('form.password')">
            <input wire:model="form.password" id="password" type="password" name="password" required
                   autocomplete="current-password" class="input">
        </x-ui.form-field>

        <label style="display:inline-flex;align-items:center;gap:8px;font-size:13px;color:var(--text-secondary);">
            <input wire:model="form.remember" id="remember" type="checkbox" name="remember" class="checkbox">
            <span>Recordarme</span>
        </label>

        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:4px;">
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" wire:navigate
                   style="font-size:13px;color:var(--primary);text-decoration:none;">
                    ¿Olvidaste tu contraseña?
                </a>
            @endif
            <x-ui.button type="submit">Ingresar</x-ui.button>
        </div>
    </form>
</div>
