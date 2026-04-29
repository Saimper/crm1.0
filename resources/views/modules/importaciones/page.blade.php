<x-app-layout>
    <x-slot name="header">
        @php $proyecto = app('tenancy.proyecto_activo'); @endphp
        <x-ui.page-header
            title="Importaciones"
            :subtitle="$proyecto->nombre"
            :back="route('proyectos.dashboard', ['proyecto_id' => $proyecto->id])"
            back-label="← Volver al proyecto" />
    </x-slot>

    @php
        $tipoOperacion = (string) $proyecto->tipo_operacion;
        $labelTipo = match ($tipoOperacion) {
            'cobranza' => 'Casos de cobranza',
            'cx'       => 'Tickets CX',
            'venta'    => 'Leads de venta',
            'servicio' => 'Casos de servicio',
            default    => 'Casos',
        };
    @endphp

    <div class="py-6" x-data="{ tab: 'personas' }">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white border border-surface-border rounded-xl shadow-card overflow-hidden">
                <nav class="flex gap-1 border-b border-surface-border px-4 pt-3" aria-label="Tabs">
                    <button type="button"
                            @click="tab = 'personas'"
                            :class="tab === 'personas' ? 'border-brand-600 text-brand-700' : 'border-transparent text-ink-500 hover:text-ink-700'"
                            class="px-4 py-2.5 -mb-px border-b-2 text-sm font-medium focus:outline-none">
                        Personas
                    </button>
                    <button type="button"
                            @click="tab = 'casos'"
                            :class="tab === 'casos' ? 'border-brand-600 text-brand-700' : 'border-transparent text-ink-500 hover:text-ink-700'"
                            class="px-4 py-2.5 -mb-px border-b-2 text-sm font-medium focus:outline-none">
                        {{ $labelTipo }}
                    </button>
                </nav>

                <div class="p-6" x-show="tab === 'personas'" x-cloak>
                    <livewire:importaciones.importar-personas />
                </div>

                <div class="p-6" x-show="tab === 'casos'" x-cloak>
                    <livewire:importaciones.importar-casos />
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
