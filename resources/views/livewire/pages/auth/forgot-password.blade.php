<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $email = '';

    /**
     * Send a password reset link to the provided email address.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        $status = Password::sendResetLink(
            $this->only('email')
        );

        if ($status != Password::RESET_LINK_SENT) {
            $this->addError('email', __($status));

            return;
        }

        $this->reset('email');

        session()->flash('status', __($status));
    }
}; ?>

<div>
    <h1 style="font-size:18px;font-weight:600;color:var(--text);margin-bottom:4px;">Recuperar contraseña</h1>
    <p style="font-size:13px;color:var(--text-secondary);margin-bottom:20px;">
        Ingresa tu email y te enviaremos un enlace para restablecer la contraseña.
    </p>

    <x-auth-session-status :status="session('status')" />

    <form wire:submit="sendPasswordResetLink" style="display:flex;flex-direction:column;gap:14px;">
        <x-ui.form-field label="Email" :error="$errors->first('email')">
            <input wire:model="email" id="email" type="email" name="email" required autofocus class="input">
        </x-ui.form-field>

        <div style="display:flex;justify-content:flex-end;">
            <x-ui.button type="submit">Enviar enlace</x-ui.button>
        </div>
    </form>
</div>
