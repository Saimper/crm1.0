<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    public function logout(Logout $logout): void
    {
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
        <a href="{{ route('profile') }}" wire:navigate
           class="sb-item" style="border-radius:6px;height:32px;">
            <x-ui.icon name="user" :size="14" />
            <span>{{ __('nav.profile') }}</span>
        </a>
        <button type="button" wire:click="logout"
                class="sb-item" style="border-radius:6px;height:32px;width:100%;color:var(--danger);">
            <x-ui.icon name="log-out" :size="14" />
            <span>{{ __('nav.logout') }}</span>
        </button>
    </div>
</div>
