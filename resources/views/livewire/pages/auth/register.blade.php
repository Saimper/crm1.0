<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $validated['password'] = Hash::make($validated['password']);

        event(new Registered($user = User::create($validated)));

        Auth::login($user);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <h1 style="font-size:18px;font-weight:600;color:var(--text);margin-bottom:4px;">Crear cuenta</h1>
    <p style="font-size:13px;color:var(--text-secondary);margin-bottom:20px;">Regístrate para comenzar.</p>

    <form wire:submit="register" style="display:flex;flex-direction:column;gap:14px;">
        <x-ui.form-field label="Nombre" :error="$errors->first('name')">
            <input wire:model="name" id="name" type="text" name="name" required autofocus
                   autocomplete="name" class="input">
        </x-ui.form-field>

        <x-ui.form-field label="Email" :error="$errors->first('email')">
            <input wire:model="email" id="email" type="email" name="email" required
                   autocomplete="username" class="input">
        </x-ui.form-field>

        <x-ui.form-field label="Contraseña" :error="$errors->first('password')">
            <input wire:model="password" id="password" type="password" name="password" required
                   autocomplete="new-password" class="input">
        </x-ui.form-field>

        <x-ui.form-field label="Confirmar contraseña" :error="$errors->first('password_confirmation')">
            <input wire:model="password_confirmation" id="password_confirmation" type="password"
                   name="password_confirmation" required autocomplete="new-password" class="input">
        </x-ui.form-field>

        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:4px;">
            <a href="{{ route('login') }}" wire:navigate
               style="font-size:13px;color:var(--primary);text-decoration:none;">
                ¿Ya estás registrado?
            </a>
            <x-ui.button type="submit">Registrarse</x-ui.button>
        </div>
    </form>
</div>
