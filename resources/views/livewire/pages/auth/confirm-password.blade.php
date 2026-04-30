<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $password = '';

    /**
     * Confirm the current user's password.
     */
    public function confirmPassword(): void
    {
        $this->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('web')->validate([
            'email' => Auth::user()->email,
            'password' => $this->password,
        ])) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        session(['auth.password_confirmed_at' => time()]);

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <h1 style="font-size:18px;font-weight:600;color:var(--text);margin-bottom:4px;">Confirma tu contraseña</h1>
    <p style="font-size:13px;color:var(--text-secondary);margin-bottom:20px;">
        Esta es un área segura. Confirma tu contraseña para continuar.
    </p>

    <form wire:submit="confirmPassword" style="display:flex;flex-direction:column;gap:14px;">
        <x-ui.form-field label="Contraseña" :error="$errors->first('password')">
            <input wire:model="password" id="password" type="password" name="password" required
                   autocomplete="current-password" class="input" autofocus>
        </x-ui.form-field>

        <div style="display:flex;justify-content:flex-end;">
            <x-ui.button type="submit">Confirmar</x-ui.button>
        </div>
    </form>
</div>
