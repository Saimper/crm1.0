<x-app-layout>
    @php
        /** @var \App\Models\User|null $u */
        $u = auth()->user();
        $esAdminGlobal = $u?->esAdminGlobal() ?? false;

        $tiles = [
            [
                'route' => 'admin.mandantes',
                'title' => __('tenancy.tile_mandantes_title'),
                'desc'  => __('tenancy.tile_mandantes_desc'),
                'icon'  => 'building',
                'solo_admin_global' => true,
            ],
            [
                'route' => 'admin.proyectos',
                'title' => __('tenancy.tile_proyectos_title'),
                'desc'  => __('tenancy.tile_proyectos_desc'),
                'icon'  => 'folder',
                'solo_admin_global' => false,
            ],
            [
                'route' => 'admin.usuarios',
                'title' => $esAdminGlobal ? __('tenancy.tile_usuarios_global_title') : __('tenancy.tile_usuarios_mandante_title'),
                'desc'  => $esAdminGlobal
                    ? __('tenancy.tile_usuarios_global_desc')
                    : __('tenancy.tile_usuarios_mandante_desc'),
                'icon'  => 'users',
                'solo_admin_global' => false,
            ],
            [
                'route' => 'admin.campos-personalizados',
                'title' => __('tenancy.tile_campos_title'),
                'desc'  => __('tenancy.tile_campos_desc'),
                'icon'  => 'hash',
                'solo_admin_global' => true,
            ],
            [
                'route' => 'admin.entidades-configurables',
                'title' => __('tenancy.tile_entidades_title'),
                'desc'  => __('tenancy.tile_entidades_desc'),
                'icon'  => 'layers',
                'solo_admin_global' => true,
            ],
            [
                'route' => 'admin.auditoria',
                'title' => $esAdminGlobal ? __('tenancy.tile_auditoria_global_title') : __('tenancy.tile_auditoria_mandante_title'),
                'desc'  => $esAdminGlobal ? __('tenancy.tile_auditoria_global_desc') : __('tenancy.tile_auditoria_mandante_desc'),
                'icon'  => 'shield',
                'solo_admin_global' => false,
            ],
        ];

        $tiles = array_filter($tiles, fn ($t) => $esAdminGlobal || ! $t['solo_admin_global']);
    @endphp

    <div class="page">
        <div class="page-header">
            <div>
                <h1 class="page-title">{{ $esAdminGlobal ? __('tenancy.dashboard_title_global') : __('tenancy.dashboard_title_mandante') }}</h1>
                <div class="page-subtitle">
                    {{ $esAdminGlobal ? __('tenancy.dashboard_subtitle_global') : __('tenancy.dashboard_subtitle_mandante') }}
                </div>
            </div>
            <div style="display:flex;gap:8px;">
                <a href="{{ route('dashboard') }}" wire:navigate class="btn btn-ghost btn-sm">{{ __('tenancy.back_to_selector') }}</a>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px;">
            @foreach($tiles as $t)
                @if(\Illuminate\Support\Facades\Route::has($t['route']))
                    <a href="{{ route($t['route']) }}" wire:navigate
                       class="card card-pad admin-tile"
                       style="text-decoration:none;color:inherit;display:block;transition:border-color 120ms var(--ease),background 120ms var(--ease);">
                        <div style="display:flex;align-items:flex-start;gap:12px;">
                            <div style="flex-shrink:0;height:40px;width:40px;border-radius:8px;background:var(--primary-soft);color:var(--primary-text);display:flex;align-items:center;justify-content:center;border:1px solid var(--primary-soft-border);">
                                <x-ui.icon :name="$t['icon']" :size="18" />
                            </div>
                            <div style="min-width:0;">
                                <div style="font-weight:600;color:var(--text);font-size:14px;">{{ $t['title'] }}</div>
                                <p style="margin-top:4px;font-size:12px;color:var(--text-tertiary);line-height:1.5;">{{ $t['desc'] }}</p>
                            </div>
                        </div>
                    </a>
                @endif
            @endforeach
        </div>

        <style>
            .admin-tile:hover { border-color: var(--primary-soft-border); background: var(--bg-subtle); }
        </style>
    </div>
</x-app-layout>
