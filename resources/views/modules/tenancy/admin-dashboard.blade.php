<x-app-layout>
    @php
        /** @var \App\Models\User|null $u */
        $u = auth()->user();
        $esAdminGlobal = $u?->esAdminGlobal() ?? false;

        $tiles = [
            [
                'route' => 'admin.mandantes',
                'title' => 'Mandantes',
                'desc'  => 'Empresas externas que delegan procesos al BPO.',
                'icon'  => 'building',
                'solo_admin_global' => true,
            ],
            [
                'route' => 'admin.proyectos',
                'title' => 'Proyectos',
                'desc'  => 'Contextos operativos por mandante (cobranza, CX, venta, servicio).',
                'icon'  => 'folder',
                'solo_admin_global' => false,
            ],
            [
                'route' => 'admin.usuarios',
                'title' => $esAdminGlobal ? 'Usuarios globales' : 'Usuarios del mandante',
                'desc'  => $esAdminGlobal
                    ? 'Cuentas, ADMIN_GLOBAL y asignación de roles por proyecto.'
                    : 'Asignación de roles a usuarios en proyectos del mandante.',
                'icon'  => 'users',
                'solo_admin_global' => false,
            ],
            [
                'route' => 'admin.campos-personalizados',
                'title' => 'Campos personalizados',
                'desc'  => 'Definir, editar y desactivar campos por proyecto.',
                'icon'  => 'hash',
                'solo_admin_global' => true,
            ],
            [
                'route' => 'admin.entidades-configurables',
                'title' => 'Entidades configurables',
                'desc'  => 'Tablas tipadas (pólizas, vehículos, etc.) por proyecto/cartera.',
                'icon'  => 'layers',
                'solo_admin_global' => true,
            ],
            [
                'route' => 'admin.auditoria',
                'title' => $esAdminGlobal ? 'Auditoría global' : 'Auditoría del mandante',
                'desc'  => 'Eventos del sistema (creación, edición, eliminación).',
                'icon'  => 'shield',
                'solo_admin_global' => false,
            ],
        ];

        $tiles = array_filter($tiles, fn ($t) => $esAdminGlobal || ! $t['solo_admin_global']);
    @endphp

    <div class="page">
        <div class="page-header">
            <div>
                <h1 class="page-title">{{ $esAdminGlobal ? 'Administración global' : 'Administración del mandante' }}</h1>
                <div class="page-subtitle">
                    {{ $esAdminGlobal ? 'Configuración cross-project · ADMIN_GLOBAL' : 'Proyectos y usuarios del mandante · ADMIN_MANDANTE' }}
                </div>
            </div>
            <div style="display:flex;gap:8px;">
                <a href="{{ route('dashboard') }}" wire:navigate class="btn btn-ghost btn-sm">← Selector de proyectos</a>
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
