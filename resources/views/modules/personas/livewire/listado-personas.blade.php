<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Clientes</h1>
            <div class="page-subtitle">{{ numero_local($totalProyecto, 0).' personas con '.$rotuloCasos.' en carteras activas' }}</div>
        </div>
        <div class="flex items-center gap-2">
            @can('personas.crear', app('tenancy.proyecto_activo')->id)
                <a href="{{ route('proyectos.personas.crear', ['proyecto_id' => app('tenancy.proyecto_activo')->id]) }}"
                   wire:navigate class="btn btn-primary">
                    <x-ui.icon name="plus" :size="14" />
                    {{ __('personas.new_person') }}
                </a>
            @endcan
        </div>
    </div>

    <div class="card">
        <x-ui.toolbar :count="__('personas.results', ['count' => $personas->total()])">
            <x-ui.search-input width="300px" wire:model.live.debounce.300ms="busqueda"
                               placeholder="Buscar persona, identificación o cuenta…" />
            <select wire:model.live="tipoPersona" class="input" style="width:160px;">
                <option value="">{{ __('personas.all_types') }}</option>
                <option value="fisica">{{ __('personas.type_physical') }}</option>
                <option value="juridica">{{ __('personas.type_legal') }}</option>
            </select>
            @if($busqueda !== '' || $tipoPersona !== '')
                <button type="button" wire:click="limpiarFiltros" class="btn btn-ghost btn-sm">{{ __('personas.clear_filters') }}</button>
            @endif
            @can('personas.exportar', (int) app('tenancy.proyecto_activo')->id)
                {{-- Sin wire:navigate: es una descarga, no una pantalla. El href lleva los filtros puestos. --}}
                <a href="{{ $urlExportar }}" class="btn btn-secondary btn-sm">
                    <x-ui.icon name="download" :size="13" />
                    {{ __('personas.export_csv') }}
                </a>
            @endcan
        </x-ui.toolbar>

        {{-- La lista de antes se queda en pantalla mientras llega la nueva; sólo
             esta barra dice que se está trabajando. --}}
        <x-ui.cargando />

        @if($personas->isEmpty())
            <div class="empty">
                <div class="empty-icon"><x-ui.icon name="user" :size="32" /></div>
                <div class="empty-title">Sin {{ $rotuloCasos }} en operación</div>
                <div class="empty-desc">
                    @if($busqueda !== '' || $tipoPersona !== '')
                        {{ __('personas.empty_with_filters') }}
                    @else
                        {{ 'Las personas aparecen aquí cuando tienen una cuenta en una cartera activa. Las cuentas retiradas se consultan en Histórico.' }}
                    @endif
                </div>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="table table-compact">
                    <thead>
                        <tr>
                            <th>Persona</th>
                            <th>{{ ucfirst($rotuloCasos) }} de la persona</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($personas as $p)
                            @php
                                $nombre = trim(($p->nombres ?? '').' '.($p->apellidos ?? '')) ?: (string) ($p->razon_social ?? '');
                                $url = route('proyectos.trabajo', ['proyecto_id' => app('tenancy.proyecto_activo')->id, 'persona' => $p->public_id]);
                            @endphp
                            <tr wire:key="persona-{{ $p->id }}">
                                <td class="align-top min-w-[200px]">
                                    <a href="{{ $url }}" wire:navigate class="font-semibold text-ink hover:text-brand-500">{{ $nombre ?: '—' }}</a>
                                    <div class="font-mono text-xs text-ink-500 mt-1">{{ $p->tipo_identificacion_codigo }} {{ $p->identificacion }}</div>
                                    <div class="text-xs text-ink-500 mt-2">{{ numero_local($p->total_casos, 0) }} {{ $rotuloCasos }} en operación</div>
                                </td>
                                <td class="min-w-[420px]">
                                    <div class="grid gap-2">
                                        @foreach($cuentasPorPersona->get($p->id, collect()) as $cuenta)
                                            <a href="{{ $url }}?caso={{ $cuenta->public_id }}" wire:navigate
                                               class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border p-3 hover:bg-surface-100">
                                                <div>
                                                    <div class="font-mono text-sm font-semibold text-ink">{{ $cuenta->referencia }}</div>
                                                    <div class="text-xs text-ink-500 mt-1">{{ $cuenta->cartera_nombre }} · {{ $cuenta->estado_nombre }}</div>
                                                </div>
                                                @if($cuenta->tipo_caso === 'cobranza')
                                                    <div class="text-right">
                                                        <div class="font-mono font-medium text-ink">{{ $cuenta->moneda }} {{ $cuenta->saldo_total === null ? '—' : numero_local($cuenta->saldo_total) }}</div>
                                                        <div class="text-xs text-ink-500 mt-1">Mora: {{ $cuenta->dias_mora === null ? 'Sin dato' : numero_local($cuenta->dias_mora, 0).' días' }}</div>
                                                    </div>
                                                @endif
                                                <x-ui.icon name="chevron-right" :size="14" />
                                            </a>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="card-footer">
                {{ $personas->links() }}
            </div>
        @endif
    </div>
</div>
