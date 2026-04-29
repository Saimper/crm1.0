<div class="py-8">
    <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

        @if($esAdminGlobal)
            <x-ui.card class="bg-brand-50 border-brand-200">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <div class="text-xs uppercase tracking-wider text-brand-800 font-semibold">Administrador global</div>
                        <p class="mt-1 text-sm text-brand-900">Acceso cross-project a administración y reportes consolidados.</p>
                    </div>
                    <x-ui.button as="a" :href="route('admin.dashboard')">
                        Ir a administración
                    </x-ui.button>
                </div>
            </x-ui.card>
        @endif

        <section>
            <x-ui.section-title
                title="Proyectos disponibles"
                :hint="$proyectos->count() . ' ' . ($proyectos->count() === 1 ? 'proyecto' : 'proyectos')" />

            @if($proyectos->isEmpty())
                <x-ui.empty-state
                    title="Sin proyectos asignados"
                    message="Contacta a tu supervisor o al administrador para obtener acceso." />
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($proyectos as $p)
                        @php
                            $tipoTone = match ($p->tipo_operacion) {
                                'cobranza' => 'warning',
                                'cx'       => 'info',
                                'venta'    => 'success',
                                'servicio' => 'accent',
                                default    => 'neutral',
                            };
                        @endphp
                        <a href="{{ route('proyectos.dashboard', ['proyecto_id' => $p->id]) }}"
                           wire:navigate
                           class="group block rounded-xl border border-surface-border bg-white p-5 shadow-card hover:shadow-card-hover hover:border-brand-300 transition-all">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="text-[11px] uppercase tracking-wider text-ink-500 font-medium truncate">{{ $p->mandante_nombre }}</div>
                                    <h3 class="mt-0.5 font-semibold text-ink-900 group-hover:text-brand-700 transition-colors truncate">{{ $p->nombre }}</h3>
                                </div>
                                <x-ui.badge :tone="$tipoTone">{{ ucfirst($p->tipo_operacion) }}</x-ui.badge>
                            </div>
                            <div class="mt-4 text-xs text-ink-500 font-mono">{{ $p->codigo }}</div>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</div>
