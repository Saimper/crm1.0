<x-app-layout>
    @php
        $proyecto = app('tenancy.proyecto_activo');
        $tipoOperacion = (string) $proyecto->tipo_operacion;

        $tabsComunes = [
            'resultados'    => 'Resultados',
            'tipos_gestion' => 'Tipos de gestión',
            'causas'        => 'Causas',
            'motivos'       => 'Motivos no contacto',
            'estados_caso'  => 'Estados de caso',
        ];

        $tabsTipo = match ($tipoOperacion) {
            'cobranza' => [
                'tramos_mora' => 'Tramos de mora',
                'tipos_pago'  => 'Tipos de pago',
            ],
            'cx' => [
                'categorias_ticket'     => 'Categorías ticket',
                'prioridades_ticket'    => 'Prioridades',
                'niveles_sla'           => 'Niveles SLA',
                'niveles_escalamiento'  => 'Escalamiento',
            ],
            'venta' => [
                'productos_venta' => 'Productos',
                'etapas_embudo'   => 'Etapas embudo',
            ],
            'servicio' => [
                'tipos_accion_servicio' => 'Tipos de acción',
                'estados_tecnicos'      => 'Estados técnicos',
            ],
            default => [],
        };

        $tabs = $tabsComunes + $tabsTipo;
    @endphp

    <div class="page">
        <div class="page-header">
            <div>
                <h1 class="page-title">Catálogos del proyecto</h1>
                <div class="page-subtitle">{{ $proyecto->nombre }}</div>
            </div>
            <div style="display:flex;gap:8px;">
                <a href="{{ route('proyectos.dashboard', ['proyecto_id' => $proyecto->id]) }}"
                   wire:navigate class="btn btn-ghost btn-sm">← Volver al proyecto</a>
            </div>
        </div>

        <div class="space-y-4" x-data="{ tab: 'resultados' }">

            <nav class="flex items-center flex-wrap gap-1 border-b border-surface-border text-sm bg-white rounded-t-xl px-2 pt-1">
                @foreach($tabs as $key => $label)
                    <button type="button"
                            x-on:click="tab = '{{ $key }}'"
                            :class="tab === '{{ $key }}' ? 'border-brand-600 text-brand-700 font-semibold' : 'border-transparent text-ink-600 hover:text-ink-900'"
                            class="px-4 py-2.5 border-b-2 -mb-px text-sm transition-colors">
                        {{ $label }}
                    </button>
                @endforeach
            </nav>

            {{-- Comunes --}}
            <section x-show="tab === 'resultados'" x-cloak>
                <livewire:catalogos.admin-resultados :key="'cat-res'" />
            </section>
            <section x-show="tab === 'tipos_gestion'" x-cloak>
                <livewire:catalogos.admin-tipos-gestion :key="'cat-tg'" />
            </section>
            <section x-show="tab === 'causas'" x-cloak>
                <livewire:catalogos.admin-causas-gestion :key="'cat-ca'" />
            </section>
            <section x-show="tab === 'motivos'" x-cloak>
                <livewire:catalogos.admin-motivos-no-contacto :key="'cat-mnc'" />
            </section>
            <section x-show="tab === 'estados_caso'" x-cloak>
                <livewire:catalogos.admin-estados-caso :key="'cat-ec'" />
            </section>

            @if($tipoOperacion === 'cobranza')
                <section x-show="tab === 'tramos_mora'" x-cloak>
                    <livewire:cobranza.admin-tramos-mora :key="'cat-tramos'" />
                </section>
                <section x-show="tab === 'tipos_pago'" x-cloak>
                    <livewire:cobranza.admin-tipos-pago :key="'cat-tpago'" />
                </section>
            @endif

            @if($tipoOperacion === 'cx')
                <section x-show="tab === 'categorias_ticket'" x-cloak>
                    <livewire:cx.admin-categorias-ticket :key="'cat-cat'" />
                </section>
                <section x-show="tab === 'prioridades_ticket'" x-cloak>
                    <livewire:cx.admin-prioridades-ticket :key="'cat-pri'" />
                </section>
                <section x-show="tab === 'niveles_sla'" x-cloak>
                    <livewire:cx.admin-niveles-sla :key="'cat-sla'" />
                </section>
                <section x-show="tab === 'niveles_escalamiento'" x-cloak>
                    <livewire:cx.admin-niveles-escalamiento :key="'cat-esc'" />
                </section>
            @endif

            @if($tipoOperacion === 'venta')
                <section x-show="tab === 'productos_venta'" x-cloak>
                    <livewire:venta.admin-productos-venta :key="'cat-prod'" />
                </section>
                <section x-show="tab === 'etapas_embudo'" x-cloak>
                    <livewire:venta.admin-etapas-embudo :key="'cat-etap'" />
                </section>
            @endif

            @if($tipoOperacion === 'servicio')
                <section x-show="tab === 'tipos_accion_servicio'" x-cloak>
                    <livewire:servicio.admin-tipos-accion-servicio :key="'cat-tas'" />
                </section>
                <section x-show="tab === 'estados_tecnicos'" x-cloak>
                    <livewire:servicio.admin-estados-tecnicos :key="'cat-estec'" />
                </section>
            @endif
        </div>
    </div>
</x-app-layout>
