<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    /**
     * Send an email verification notification to the user.
     */
    public function sendVerification(): void
    {
        if (Auth::user()->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);

            return;
        }

        Auth::user()->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div>
    <h1 style="font-size:18px;font-weight:600;color:var(--text);margin-bottom:4px;">{{ __('auth.verify_title') }}</h1>
    <p style="font-size:13px;color:var(--text-secondary);margin-bottom:16px;">
        {{ __('auth.verify_subtitle') }}
    </p>

    @if (session('status') == 'verification-link-sent')
        <x-ui.alert tone="success">{{ __('auth.verify_sent') }}</x-ui.alert>
    @endif

    <div style="display:flex;align-items:center;justify-content:space-between;margin-top:16px;">
        <x-ui.button wire:click="sendVerification">{{ __('auth.verify_resend') }}</x-ui.button>
        <button type="button" wire:click="logout"
                style="background:transparent;border:0;color:var(--text-secondary);font-size:13px;cursor:pointer;text-decoration:underline;">
            {{ __('nav.logout') }}
        </button>
    </div>
</div>
