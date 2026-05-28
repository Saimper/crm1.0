<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">{{ __('compromisos.title_list') }}</h1>
            <div class="page-subtitle">
                <strong>{{ $resumen['pendientes'] }}</strong> {{ __('compromisos.state_pending') }} ·
                <span style="color:var(--danger);"><strong>{{ $resumen['vencidos'] }}</strong> {{ __('compromisos.filter_expired') }}</span> ·
                {{ $resumen['cumplidos'] }} {{ __('compromisos.state_fulfilled') }} · {{ $resumen['rotos'] }} {{ __('compromisos.state_broken') }}
            </div>
        </div>
    </div>

    <div class="card" style="padding:0;">
        <div style="padding:12px 16px;border-bottom:1px solid var(--border);display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
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
            <span style="flex:1;"></span>
            <span style="font-size:12px;color:var(--text-tertiary);">{{ __('compromisos.results', ['count' => $compromisos->total()]) }}</span>
        </div>

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
                                'pendiente' => $c->fecha_vencimiento < now()->format('Y-m-d') ? 'danger' : 'warning',
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
                            @if($url) onclick="window.Livewire.navigate('{{ $url }}')" style="cursor:pointer;" @endif>
                            <td>
                                <span style="font-size:11px;">
                                    {{ str_replace('_', ' ', $c->tipo_compromiso) }}
                                </span>
                            </td>
                            <td>
                                <x-ui.badge :tone="$estadoTone" size="sm">
                                    {{ ucfirst($c->estado) }}
                                </x-ui.badge>
                            </td>
                            <td><span style="font-weight:500;">{{ $nombre !== '' ? $nombre : '—' }}</span></td>
                            <td><span class="font-mono" style="font-size:12px;">{{ $c->identificacion }}</span></td>
                            <td style="font-size:12px;color:var(--text-secondary);">{{ $c->usuario_nombre ?? '—' }}</td>
                            <td style="font-size:12px;">
                                {{ $c->fecha_vencimiento ? \Illuminate\Support\Carbon::parse($c->fecha_vencimiento)->format('d/m/Y') : '—' }}
                            </td>
                            <td style="font-size:12px;color:var(--text-secondary);">
                                {{ $c->fecha_resolucion ? \Illuminate\Support\Carbon::parse($c->fecha_resolucion)->format('d/m/Y') : '—' }}
                            </td>
                            <td>
                                @if($url)
                                    <x-ui.icon name="chevron-right" :size="14" style="color:var(--text-muted);" />
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div style="padding:10px 16px;border-top:1px solid var(--border);">
                {{ $compromisos->links() }}
            </div>
        @endif
    </div>
</div>
