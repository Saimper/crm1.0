<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">{{ __('compromisos.title_list') }}</h1>
            <div class="page-subtitle">
                <strong>{{ $resumen['pendientes'] }}</strong> {{ __('compromisos.state_pending') }} ·
                <span style="color:var(--danger);"><strong>{{ $resumen['vencidos'] }}</strong> {{ __('compromisos.filter_expired') }}</span> ·
                {{ $resumen['cumplidos'] }} {{ __('compromisos.state_fulfilled') }} · {{ $resumen['rotos'] }} {{ __('compromisos.state_broken') }}
            </div>
            <p class="text-sm text-ink-500 mt-2">
                Se muestran compromisos de carteras activas. Los de carteras archivadas se conservan en
                @can('historico.ver', (int) app('tenancy.proyecto_activo')->id)
                    <a href="{{ route('proyectos.historico.lista', ['proyecto_id' => app('tenancy.proyecto_activo')->id]) }}" wire:navigate class="text-brand-500 hover:underline">Histórico</a>.
                @else
                    Histórico, bajo consulta de supervisión.
                @endcan
            </p>
        </div>
    </div>

    <div class="card">
        <x-ui.toolbar :count="__('compromisos.results', ['count' => $compromisos->total()])">
            <select wire:model.live="estado" class="input" style="width:160px;">
                <option value="">{{ __('compromisos.all_states') }}</option>
                <option value="pendiente">{{ __('compromisos.state_pending') }}</option>
                <option value="cumplido">{{ __('compromisos.state_fulfilled') }}</option>
                <option value="roto">{{ __('compromisos.state_broken') }}</option>
                <option value="cancelado">{{ __('compromisos.state_cancelled') }}</option>
            </select>
            <select wire:model.live="vencimiento" class="input" style="width:160px;">
                <option value="">{{ __('compromisos.any_expiry') }}</option>
                <option value="vigentes">{{ __('compromisos.filter_active') }}</option>
                <option value="vencidos">{{ __('compromisos.filter_expired') }}</option>
                <option value="proximos7d">{{ __('compromisos.filter_next7d') }}</option>
            </select>
            <select wire:model.live="tipoCompromiso" class="input" style="width:200px;">
                <option value="">{{ __('compromisos.all_types') }}</option>
                <option value="promesa_pago">{{ __('compromisos.type_promise') }}</option>
                <option value="resolucion_ticket">{{ __('compromisos.type_resolution') }}</option>
                <option value="cierre_venta">{{ __('compromisos.type_close') }}</option>
                <option value="accion_servicio">{{ __('compromisos.type_service') }}</option>
            </select>
            @if($estado !== '' || $vencimiento !== '' || $tipoCompromiso !== '')
                <button type="button" wire:click="limpiarFiltros" class="btn btn-ghost btn-sm">{{ __('compromisos.clear_filters') }}</button>
            @endif
            @can('compromisos.exportar', (int) app('tenancy.proyecto_activo')->id)
                {{-- Sin wire:navigate: es una descarga, no una pantalla. El href lleva los filtros puestos. --}}
                <a href="{{ $urlExportar }}" class="btn btn-secondary btn-sm">
                    <x-ui.icon name="download" :size="13" />
                    {{ __('compromisos.export_csv') }}
                </a>
            @endcan
        </x-ui.toolbar>

        {{-- La lista de antes se queda en pantalla mientras llega la nueva; sólo
             esta barra dice que se está trabajando. --}}
        <x-ui.cargando />

        @if($compromisos->isEmpty())
            <div class="empty">
                <div class="empty-icon"><x-ui.icon name="tag" :size="32" /></div>
                <div class="empty-title">{{ __('compromisos.empty_title') }}</div>
                <div class="empty-desc">{{ __('compromisos.empty_desc') }}</div>
            </div>
        @else
            <table class="table table-compact table-clickable">
                <thead>
                    <tr>
                        <th style="width:130px;">{{ __('compromisos.col_type') }}</th>
                        <th style="width:100px;">{{ __('compromisos.col_state') }}</th>
                        <th>{{ __('compromisos.col_person') }}</th>
                        <th>{{ __('compromisos.col_id_doc') }}</th>
                        <th>{{ __('compromisos.col_user') }}</th>
                        <th style="width:120px;">{{ __('compromisos.col_expiry') }}</th>
                        <th style="width:120px;">{{ __('compromisos.col_resolved') }}</th>
                        <th style="width:60px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($compromisos as $c)
                        @php
                            $nombre = $c->tipo_persona === 'juridica'
                                ? ($c->razon_social ?? '—')
                                : trim(($c->nombres ?? '').' '.($c->apellidos ?? ''));
                            $estadoTone = match ($c->estado) {
                                'cumplido' => 'success',
                                'roto' => 'danger',
                                'cancelado' => 'neutral',
                                'pendiente' => $c->fecha_vencimiento < $hoy ? 'danger' : 'warning',
                                default => 'neutral',
                            };
                            $url = $c->persona_public_id
                                ? route('proyectos.trabajo', [
                                    'proyecto_id' => app('tenancy.proyecto_activo')->id,
                                    'persona' => $c->persona_public_id,
                                    'caso' => $c->caso_public_id,
                                ])
                                : null;
                        @endphp
                        <tr wire:key="comp-{{ $c->id }}"
                            @if($url) onclick="window.Livewire.navigate('{{ $url }}')" @endif>
                            <td>
                                <span class="text-xs">
                                    {{ str_replace('_', ' ', $c->tipo_compromiso) }}
                                </span>
                            </td>
                            <td>
                                <x-ui.badge :tone="$estadoTone" size="sm">
                                    {{ ucfirst($c->estado) }}
                                </x-ui.badge>
                            </td>
                            <td><span class="font-medium">{{ $nombre !== '' ? $nombre : '—' }}</span></td>
                            <td><span class="font-mono text-sm">{{ $c->identificacion }}</span></td>
                            <td class="text-sm text-ink-600">{{ $c->usuario_nombre ?? '—' }}</td>
                            <td class="text-sm">
                                {{ $c->fecha_vencimiento ? fecha_local($c->fecha_vencimiento) : '—' }}
                            </td>
                            <td class="text-sm text-ink-600">
                                {{ $c->fecha_resolucion ? fecha_local($c->fecha_resolucion) : '—' }}
                            </td>
                            <td class="text-ink-400">
                                @if($url)
                                    <x-ui.icon name="chevron-right" :size="14" />
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="card-footer">
                {{ $compromisos->links() }}
            </div>
        @endif
    </div>
</div>
