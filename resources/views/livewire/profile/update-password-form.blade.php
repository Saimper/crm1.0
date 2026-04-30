<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component
{
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Update the password for the currently authenticated user.
     */
    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => ['required', 'string', 'current_password'],
                'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        Auth::user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        $this->dispatch('password-updated');
    }
}; ?>

<section>
    <p style="font-size:13px;color:var(--text-secondary);margin-bottom:16px;">
        Usa una contraseña larga y aleatoria para mantener tu cuenta segura.
    </p>

    <form wire:submit="updatePassword" style="display:flex;flex-direction:column;gap:14px;">
        <x-ui.form-field label="Contraseña actual" :error="$errors->first('current_password')">
            <input wire:model="current_password" id="update_password_current_password" name="current_password"
                   type="password" autocomplete="current-password" class="input">
        </x-ui.form-field>

        <x-ui.form-field label="Nueva contraseña" :error="$errors->first('password')">
            <input wire:model="password" id="update_password_password" name="password"
                   type="password" autocomplete="new-password" class="input">
        </x-ui.form-field>

        <x-ui.form-field label="Confirmar contraseña" :error="$errors->first('password_confirmation')">
            <input wire:model="password_confirmation" id="update_password_password_confirmation"
                   name="password_confirmation" type="password" autocomplete="new-password" class="input">
        </x-ui.form-field>

        <div style="display:flex;align-items:center;gap:12px;">
            <x-ui.button type="submit">Guardar</x-ui.button>
            <x-action-message on="password-updated">
                <span style="font-size:12px;color:var(--success);">Guardado.</span>
            </x-action-message>
        </div>
    </form>
</section>
