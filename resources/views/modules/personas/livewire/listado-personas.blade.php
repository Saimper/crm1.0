<div class="page client-directory" x-data="{
    menuColumnas: false,
    columnas: { documento: true, cuenta: true, cartera: false, saldo: true, mora: true, estado: false },
    claveColumnas: @js('crm-client-columns-v1-'.auth()->id().'-'.app('tenancy.proyecto_activo')->id),
    init() {
        try {
            const saved = JSON.parse(localStorage.getItem(this.claveColumnas) || '{}');
            Object.keys(this.columnas).forEach(key => { if (typeof saved?.[key] === 'boolean') this.columnas[key] = saved[key]; });
        } catch (_) {}
        this.$watch('columnas', value => { try { localStorage.setItem(this.claveColumnas, JSON.stringify(value)); } catch (_) {} });
    }
}">
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
            <div class="client-column-picker" x-on:click.outside="menuColumnas = false" x-on:keydown.escape.stop="menuColumnas = false; $refs.columnToggle.focus()">
                <button type="button" x-ref="columnToggle" class="btn btn-secondary btn-sm" aria-controls="client-column-options" :aria-expanded="menuColumnas" x-on:click="menuColumnas = !menuColumnas" title="Elegir columnas">
                    <x-ui.icon name="settings" :size="16" /> Columnas
                </button>
                <div id="client-column-options" x-show="menuColumnas" x-cloak class="client-column-options">
                    <p>Columnas visibles</p>
                    <label><input type="checkbox" checked disabled /> Cliente <span>Siempre visible</span></label>
                    @foreach(['documento' => 'Documento', 'cuenta' => ucfirst($rotuloCasos), 'cartera' => 'Cartera', 'saldo' => 'Saldo', 'mora' => 'Mora', 'estado' => 'Estado'] as $clave => $etiqueta)
                        <label><input type="checkbox" x-model="columnas.{{ $clave }}" /> {{ $etiqueta }}</label>
                    @endforeach
                    <button type="button" class="btn-link" x-on:click="columnas = { documento: true, cuenta: true, cartera: false, saldo: true, mora: true, estado: false }">Restablecer</button>
                </div>
            </div>
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
                <table class="table client-table client-configurable-table">
                    <thead>
                        <tr>
                            <th scope="col">Cliente</th>
                            @foreach(['documento' => 'Documento', 'cuenta' => ucfirst($rotuloCasos), 'cartera' => 'Cartera', 'saldo' => 'Saldo', 'mora' => 'Mora', 'estado' => 'Estado'] as $clave => $etiqueta)
                                <th scope="col" x-show="columnas.{{ $clave }}">{{ $etiqueta }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($personas as $p)
                            @php
                                $nombre = trim(($p->nombres ?? '').' '.($p->apellidos ?? '')) ?: (string) ($p->razon_social ?? '');
                                $url = route('proyectos.trabajo', ['proyecto_id' => app('tenancy.proyecto_activo')->id, 'persona' => $p->public_id]);
                                $cuentasCliente = $cuentasPorPersona->get($p->id, collect());
                            @endphp
                            <tr wire:key="persona-{{ $p->id }}" class="client-clickable-row"
                                x-on:click="if (!$event.target.closest('a,button,input,select') && !window.getSelection()?.toString()) { const link = $el.querySelector('.client-name'); if ($event.metaKey || $event.ctrlKey) { window.open(link.href, '_blank', 'noopener'); } else { link.click(); } }">
                                <td class="client-identity" data-label="Cliente">
                                    <a href="{{ $url }}" wire:navigate class="client-name">{{ $nombre !== '' && $nombre === mb_strtoupper($nombre) ? mb_convert_case($nombre, MB_CASE_TITLE, 'UTF-8') : ($nombre ?: '—') }}</a>
                                </td>
                                <td x-show="columnas.documento" data-label="Documento"><span class="client-document">{{ $p->tipo_identificacion_codigo }} {{ $p->identificacion }}</span></td>
                                <td x-show="columnas.cuenta" data-label="{{ ucfirst($rotuloCasos) }}">
                                    @foreach($cuentasCliente as $cuenta)
                                        <a class="client-column-value client-reference" href="{{ $url }}?caso={{ $cuenta->public_id }}" wire:navigate>{{ $cuenta->referencia }}</a>
                                    @endforeach
                                </td>
                                <td x-show="columnas.cartera" data-label="Cartera">
                                    @foreach($cuentasCliente as $cuenta)<span class="client-column-value">{{ $cuenta->cartera_nombre }}</span>@endforeach
                                </td>
                                <td x-show="columnas.saldo" data-label="Saldo">
                                    @foreach($cuentasCliente as $cuenta)
                                        <span class="client-column-value client-balance">{{ $cuenta->tipo_caso === 'cobranza' && $cuenta->saldo_total !== null ? $cuenta->moneda.' '.numero_local($cuenta->saldo_total) : '—' }}</span>
                                    @endforeach
                                </td>
                                <td x-show="columnas.mora" data-label="Mora">
                                    @foreach($cuentasCliente as $cuenta)
                                        <span class="client-column-value client-overdue">{{ $cuenta->tipo_caso === 'cobranza' && $cuenta->dias_mora !== null ? numero_local($cuenta->dias_mora, 0).' días' : '—' }}</span>
                                    @endforeach
                                </td>
                                <td x-show="columnas.estado" data-label="Estado">
                                    @foreach($cuentasCliente as $cuenta)<span class="client-column-value">{{ $cuenta->estado_nombre }}</span>@endforeach
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
