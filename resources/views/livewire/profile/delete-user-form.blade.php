<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public string $password = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => ['required', 'string', 'current_password'],
        ]);

        tap(Auth::user(), $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }
}; ?>

<section>
    <p style="font-size:13px;color:var(--text-secondary);margin-bottom:16px;">
        {{ __('profile.delete_hint') }}
    </p>

    <x-ui.button variant="danger" x-data="" x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')">
        {{ __('profile.delete_button') }}
    </x-ui.button>

    <x-modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable>
        <form wire:submit="deleteUser" style="padding:20px;">
            <h2 style="font-size:16px;font-weight:600;color:var(--text);margin-bottom:6px;">
                {{ __('profile.delete_confirm_title') }}
            </h2>
            <p style="font-size:13px;color:var(--text-secondary);margin-bottom:16px;">
                {{ __('profile.delete_confirm_hint') }}
            </p>

            <x-ui.form-field :label="__('common.password')" :error="$errors->first('password')">
                <input wire:model="password" id="password" name="password" type="password"
                       placeholder="{{ __('common.password') }}" class="input">
            </x-ui.form-field>

            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
                <x-ui.button variant="secondary" x-on:click="$dispatch('close')">{{ __('common.cancel') }}</x-ui.button>
                <x-ui.button variant="danger" type="submit">{{ __('profile.delete_button') }}</x-ui.button>
            </div>
        </form>
    </x-modal>
</section>
