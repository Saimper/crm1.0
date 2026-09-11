<div class="page client-directory">
    <div class="page-header">
        <div>
            <h1 class="page-title">Clientes</h1>
            <div class="page-subtitle">{{ numero_local($totalProyecto, 0) }} clientes en operación</div>
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
        <x-ui.toolbar>
            <x-ui.search-input width="100%" wire:model.live.debounce.300ms="busqueda"
                               placeholder="Buscar cliente, documento o cuenta…" />
            <select wire:model.live="tipoPersona" class="input client-type-filter" aria-label="Tipo de persona">
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
                <table class="table client-table">
                    <thead>
                        <tr>
                            <th scope="col">Cliente</th>
                            <th scope="col"><div class="client-account-grid"><span>{{ ucfirst($rotuloCasos) }}</span><span class="text-right">Saldo</span><span class="text-right">Mora</span><span class="sr-only">Abrir ficha</span></div></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($personas as $p)
                            @php
                                $nombre = trim(($p->nombres ?? '').' '.($p->apellidos ?? '')) ?: (string) ($p->razon_social ?? '');
                                $url = route('proyectos.trabajo', ['proyecto_id' => app('tenancy.proyecto_activo')->id, 'persona' => $p->public_id]);
                            @endphp
                            <tr wire:key="persona-{{ $p->id }}">
                                <td class="client-identity">
                                    <a href="{{ $url }}" wire:navigate class="client-name">{{ $nombre !== '' && $nombre === mb_strtoupper($nombre) ? mb_convert_case($nombre, MB_CASE_TITLE, 'UTF-8') : ($nombre ?: '—') }}</a>
                                    <div class="client-document">{{ $p->tipo_identificacion_codigo }} {{ $p->identificacion }}</div>

                                </td>
                                <td class="client-accounts">
                                    <div class="client-account-list">
                                        @foreach($cuentasPorPersona->get($p->id, collect()) as $cuenta)
                                            <a href="{{ $url }}?caso={{ $cuenta->public_id }}" wire:navigate
                                               class="client-account-grid client-account-link">
                                                <div class="min-w-0">
                                                    <span class="client-reference">{{ $cuenta->referencia }}</span>
                                                    <span class="client-account-meta">{{ $cuenta->cartera_nombre }} <span class="client-state">{{ $cuenta->estado_nombre }}</span></span>
                                                </div>
                                                <span class="client-balance">
                                                    @if($cuenta->tipo_caso === 'cobranza')
                                                        <span class="client-currency">{{ $cuenta->moneda }}</span> {{ $cuenta->saldo_total === null ? '—' : numero_local($cuenta->saldo_total) }}
                                                    @else
                                                        <span class="sr-only">No aplica</span>—
                                                    @endif
                                                </span>
                                                <span class="client-overdue">
                                                    @if($cuenta->tipo_caso === 'cobranza')
                                                        {{ $cuenta->dias_mora === null ? '—' : numero_local($cuenta->dias_mora, 0).' días' }}
                                                    @else
                                                        <span class="sr-only">No aplica</span>—
                                                    @endif
                                                </span>
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
