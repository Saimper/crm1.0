<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Cambia el idioma de interfaz del usuario y recarga la página actual
     * para que el middleware SetLocale aplique el nuevo locale en toda la UI.
     */
    public function setLocale(string $locale): void
    {
        $soportados = array_keys((array) config('locales.supported', []));

        if (! in_array($locale, $soportados, true)) {
            return;
        }

        Auth::user()->update(['locale' => $locale]);

        $this->redirect(request()->header('Referer') ?: route('dashboard'), navigate: true);
    }

    public function logout(Logout $logout): void
    {
        // Embebido en el iframe del wrapper: la sesión la gestiona la app
        // principal. Ignoramos el cierre aunque llegue por una petición forjada
        // (el botón ya viene deshabilitado en la UI).
        if (session()->has('crm_embedded')) {
            return;
        }

        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

@php
    $user = auth()->user();
    $iniciales = $user
        ? mb_strtoupper(mb_substr($user->name, 0, 1) . (str_contains((string) $user->name, ' ') ? mb_substr(explode(' ', $user->name)[1] ?? '', 0, 1) : ''))
        : '·';
    $rol = $user?->esAdminGlobal() ? __('nav.role_admin_global') : __('nav.role_user');
    // El CRM embebido en el iframe del wrapper no gestiona su propia sesión:
    // el cierre lo controla la app principal. session('crm_embedded') se
    // setea en el handshake SSO y es la señal de que estamos embebidos.
    $embebido = session()->has('crm_embedded');
@endphp

<div x-data="{ open: false }" class="relative" style="display:flex;align-items:center;gap:8px;padding-left:8px;margin-left:4px;border-left:1px solid var(--border);">
    <button type="button" @click="open = !open"
            style="display:flex;align-items:center;gap:8px;background:transparent;border:0;cursor:pointer;padding:0;">
        <div class="avatar">{{ $iniciales }}</div>
        <div style="line-height:1.15;text-align:left;">
            <div style="font-size:12px;font-weight:500;color:var(--text);">{{ $user?->name }}</div>
            <div style="font-size:11px;color:var(--text-tertiary);">{{ $rol }}</div>
        </div>
        <x-ui.icon name="chevron-down" :size="14" style="color:var(--text-tertiary);" />
    </button>

    <div x-show="open" @click.outside="open = false" x-transition x-cloak
         style="position:absolute;top:calc(100% + 6px);right:0;min-width:200px;background:var(--bg-elev);border:1px solid var(--border);border-radius:8px;box-shadow:0 8px 24px rgba(16,24,40,0.10);padding:6px;z-index:120;">
        @if($embebido)
            <button type="button" disabled
                    title="{{ __('nav.profile_disabled_embedded') }}"
                    class="sb-item" style="border-radius:6px;height:32px;width:100%;color:var(--text-tertiary);opacity:0.5;cursor:not-allowed;">
                <x-ui.icon name="user" :size="14" />
                <span>{{ __('nav.profile') }}</span>
            </button>
        @else
            <a href="{{ route('profile') }}" wire:navigate
               class="sb-item" style="border-radius:6px;height:32px;">
                <x-ui.icon name="user" :size="14" />
                <span>{{ __('nav.profile') }}</span>
            </a>
        @endif

        <div style="height:1px;background:var(--border);margin:6px 0;"></div>
        <div style="padding:2px 10px 4px;font-size:11px;color:var(--text-tertiary);">{{ __('nav.language') }}</div>
        @foreach(config('locales.supported') as $code => $label)
            <button type="button" wire:click="setLocale('{{ $code }}')"
                    @class(['sb-item'])
                    style="border-radius:6px;height:32px;width:100%;justify-content:space-between;">
                <span @style(['font-weight:600;color:var(--text)' => app()->getLocale() === $code])>{{ $label }}</span>
                @if(app()->getLocale() === $code)
                    <span style="color:var(--primary);">✓</span>
                @endif
            </button>
        @endforeach

        <div style="height:1px;background:var(--border);margin:6px 0;"></div>
        @if($embebido)
            <button type="button" disabled
                    title="{{ __('nav.logout_disabled_embedded') }}"
                    class="sb-item" style="border-radius:6px;height:32px;width:100%;color:var(--text-tertiary);opacity:0.5;cursor:not-allowed;">
                <x-ui.icon name="log-out" :size="14" />
                <span>{{ __('nav.logout') }}</span>
            </button>
        @else
            <button type="button" wire:click="logout"
                    class="sb-item" style="border-radius:6px;height:32px;width:100%;color:var(--danger);">
                <x-ui.icon name="log-out" :size="14" />
                <span>{{ __('nav.logout') }}</span>
            </button>
        @endif
    </div>
</div>
