<?php

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    #[Locked]
    public string $token = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Mount the component.
     */
    public function mount(string $token): void
    {
        $this->token = $token;

        $this->email = request()->string('email');
    }

    /**
     * Reset the password for the given user.
     */
    public function resetPassword(): void
    {
        $this->validate([
            'token' => ['required'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        // Here we will attempt to reset the user's password. If it is successful we
        // will update the password on an actual user model and persist it to the
        // database. Otherwise we will parse the error and return the response.
        $status = Password::reset(
            $this->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) {
                $user->forceFill([
                    'password' => Hash::make($this->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        // If the password was successfully reset, we will redirect the user back to
        // the application's home authenticated view. If there is an error we can
        // redirect them back to where they came from with their error message.
        if ($status != Password::PASSWORD_RESET) {
            $this->addError('email', __($status));

            return;
        }

        Session::flash('status', __($status));

        $this->redirectRoute('login', navigate: true);
    }
}; ?>

<div>
    <h1 style="font-size:18px;font-weight:600;color:var(--text);margin-bottom:4px;">Restablecer contraseña</h1>
    <p style="font-size:13px;color:var(--text-secondary);margin-bottom:20px;">Define una nueva contraseña.</p>

    <form wire:submit="resetPassword" style="display:flex;flex-direction:column;gap:14px;">
        <x-ui.form-field label="Email" :error="$errors->first('email')">
            <input wire:model="email" id="email" type="email" name="email" required autofocus
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

        <div style="display:flex;justify-content:flex-end;">
            <x-ui.button type="submit">Restablecer</x-ui.button>
        </div>
    </form>
</div>
