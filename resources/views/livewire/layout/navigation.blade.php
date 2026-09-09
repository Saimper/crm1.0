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

<div x-data="{ open: false }" class="relative flex shrink-0 items-center gap-2 pl-2 ml-1 border-l border-surface-border" @keydown.escape.window="if (open) { open = false; $refs.userToggle.focus() }">
    <button type="button" x-ref="userToggle" @click="open = !open" :aria-expanded="open" aria-controls="user-menu" aria-label="{{ __('nav.user_menu') }}"
            class="flex items-center gap-2 bg-transparent border-0 p-0">
        <div class="avatar">{{ $iniciales }}</div>
        {{-- En móvil queda el avatar: el nombre completo más el rol pedían 192px
             de los 390 disponibles, el 38% de la cabecera. --}}
        <div class="hidden lg:block leading-tight text-left max-w-[180px]">
            <div class="text-sm font-medium text-ink truncate" title="{{ $user?->name }}">{{ $user?->name }}</div>
            <div style="font-size:11px;color:var(--text-tertiary);">{{ $rol }}</div>
        </div>
        <x-ui.icon name="chevron-down" :size="14" style="color:var(--text-tertiary);" />
    </button>

    <div id="user-menu" x-show="open" @click.outside="open = false" x-transition x-cloak
         class="popover popover-right min-w-[220px] max-w-[calc(100vw-24px)] p-1.5 z-[120]">
        <div class="px-3 py-2 mb-1 border-b border-surface-border">
            <div class="text-base font-semibold text-ink break-words">{{ $user?->name }}</div>
            <div class="text-xs text-ink-500 break-all">{{ $user?->email }}</div>
        </div>
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
