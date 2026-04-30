<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component
{
    public string $name = '';
    public string $email = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
        ]);

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->dispatch('profile-updated', name: $user->name);
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function sendVerification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }
}; ?>

<section>
    <p style="font-size:13px;color:var(--text-secondary);margin-bottom:16px;">
        Actualiza tu nombre y email de la cuenta.
    </p>

    <form wire:submit="updateProfileInformation" style="display:flex;flex-direction:column;gap:14px;">
        <x-ui.form-field label="Nombre" :error="$errors->first('name')">
            <input wire:model="name" id="name" name="name" type="text" required autofocus
                   autocomplete="name" class="input">
        </x-ui.form-field>

        <x-ui.form-field label="Email" :error="$errors->first('email')">
            <input wire:model="email" id="email" name="email" type="email" required
                   autocomplete="username" class="input">
            @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! auth()->user()->hasVerifiedEmail())
                <div style="margin-top:8px;font-size:12px;color:var(--text-secondary);">
                    Tu email no está verificado.
                    <button type="button" wire:click.prevent="sendVerification"
                            style="background:transparent;border:0;color:var(--primary);cursor:pointer;text-decoration:underline;font-size:12px;padding:0;">
                        Reenviar verificación
                    </button>
                    @if (session('status') === 'verification-link-sent')
                        <div style="margin-top:6px;color:var(--success);font-weight:500;">
                            Enlace de verificación enviado.
                        </div>
                    @endif
                </div>
            @endif
        </x-ui.form-field>

        <div style="display:flex;align-items:center;gap:12px;">
            <x-ui.button type="submit">Guardar</x-ui.button>
            <x-action-message on="profile-updated">
                <span style="font-size:12px;color:var(--success);">Guardado.</span>
            </x-action-message>
        </div>
    </form>
</section>
