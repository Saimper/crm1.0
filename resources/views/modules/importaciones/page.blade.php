<x-app-layout>
    @php
        $proyecto = app('tenancy.proyecto_activo');
        $tipoOperacion = (string) $proyecto->tipo_operacion;
        $labelTipo = match ($tipoOperacion) {
            'cobranza' => 'Casos de cobranza',
            'cx'       => 'Tickets CX',
            'venta'    => 'Leads de venta',
            'servicio' => 'Casos de servicio',
            default    => 'Casos',
        };
    @endphp

    <div class="page" x-data="{ tab: 'personas' }">
        <div class="page-header">
            <div>
                <h1 class="page-title">Importaciones</h1>
                <div class="page-subtitle">{{ $proyecto->nombre }}</div>
            </div>
            <div style="display:flex;gap:8px;">
                <a href="{{ route('proyectos.dashboard', ['proyecto_id' => $proyecto->id]) }}"
                   wire:navigate class="btn btn-ghost btn-sm">← Volver al proyecto</a>
            </div>
        </div>

        <div class="card" style="padding:0;">
            <nav style="display:flex;gap:4px;border-bottom:1px solid var(--border);padding:8px 12px 0;" aria-label="Tabs">
                <button type="button"
                        @click="tab = 'personas'"
                        :class="tab === 'personas' ? 'tab-active' : ''"
                        class="tab-btn">Personas</button>
                <button type="button"
                        @click="tab = 'casos'"
                        :class="tab === 'casos' ? 'tab-active' : ''"
                        class="tab-btn">{{ $labelTipo }}</button>
            </nav>

            <div style="padding:16px;" x-show="tab === 'personas'" x-cloak>
                <livewire:importaciones.importar-personas />
            </div>
            <div style="padding:16px;" x-show="tab === 'casos'" x-cloak>
                <livewire:importaciones.importar-casos />
            </div>
        </div>

        <style>
            .tab-btn {
                padding: 8px 14px;
                margin-bottom: -1px;
                border-bottom: 2px solid transparent;
                font-size: 13px;
                font-weight: 500;
                color: var(--text-secondary);
                background: transparent;
                cursor: pointer;
            }
            .tab-btn:hover { color: var(--text); }
            .tab-active {
                color: var(--primary-text) !important;
                border-bottom-color: var(--primary) !important;
            }
        </style>
    </div>
</x-app-layout>
