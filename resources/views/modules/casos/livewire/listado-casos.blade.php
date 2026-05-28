<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">{{ __('casos.title_list') }}</h1>
            <div class="page-subtitle">{{ __('casos.subtitle_open', ['count' => $totalProyecto]) }}</div>
        </div>
    </div>

    <div class="card" style="padding:0;">
        <div style="padding:12px 16px;border-bottom:1px solid var(--border);display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <div style="position:relative;width:280px;">
                <span style="position:absolute;left:9px;top:11px;color:var(--text-muted);pointer-events:none;">
                    <x-ui.icon name="search" :size="13" />
                </span>
                <input type="text" wire:model.live.debounce.300ms="busqueda"
                       class="input" placeholder="{{ __('casos.search_placeholder') }}" style="padding-left:28px;"/>
            </div>
            <select wire:model.live="carteraId" class="input" style="width:180px;">
                <option value="">{{ __('casos.all_wallets') }}</option>
                @foreach($carteras as $c)
                    <option value="{{ $c->id }}">{{ $c->nombre }}</option>
                @endforeach
            </select>
            <select wire:model.live="estadoCasoId" class="input" style="width:180px;">
                <option value="">{{ __('casos.all_states') }}</option>
                @foreach($estados as $e)
                    <option value="{{ $e->id }}">{{ $e->nombre }}</option>
                @endforeach
            </select>
            @if($busqueda !== '' || $carteraId !== '' || $estadoCasoId !== '')
                <button type="button" wire:click="limpiarFiltros" class="btn btn-ghost btn-sm">{{ __('casos.clear_filters') }}</button>
            @endif
            <span style="flex:1;"></span>
            <span style="font-size:12px;color:var(--text-tertiary);">{{ __('casos.results', ['count' => $casos->total()]) }}</span>
        </div>

        @if($casos->isEmpty())
            <div class="empty">
                <div class="empty-icon"><x-ui.icon name="folder" :size="32" /></div>
                <div class="empty-title">{{ __('casos.empty_title') }}</div>
                <div class="empty-desc">
                    @if($busqueda !== '' || $carteraId !== '' || $estadoCasoId !== '')
                        {{ __('casos.empty_with_filters') }}
                    @else
                        {{ __('casos.empty_no_filters') }}
                    @endif
                </div>
            </div>
        @else
            <table class="table table-compact table-clickable">
                <thead>
                    <tr>
                        <th style="width:100px;">{{ __('casos.col_type') }}</th>
                        <th>{{ __('casos.col_person') }}</th>
                        <th>{{ __('casos.col_id_doc') }}</th>
                        <th>{{ __('casos.col_wallet') }}</th>
                        <th>{{ __('casos.col_state') }}</th>
                        <th class="num" style="width:60px;">{{ __('casos.col_priority') }}</th>
                        <th style="width:100px;">{{ __('casos.col_commitment') }}</th>
                        <th style="width:60px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($casos as $caso)
                        @php
                            $nombre = $caso->tipo_persona === 'juridica'
                                ? ($caso->razon_social ?? '—')
                                : trim(($caso->nombres ?? '').' '.($caso->apellidos ?? ''));
                            $tipoTone = match ($caso->tipo_caso) {
                                'cobranza'   => 'warning',
                                'ticket_cx'  => 'info',
                                'lead_venta' => 'success',
                                'servicio'   => 'primary',
                                default      => 'neutral',
                            };
                            $url = route('proyectos.trabajo', [
                                'proyecto_id' => app('tenancy.proyecto_activo')->id,
                                'persona' => $caso->persona_public_id,
                                'caso' => $caso->public_id,
                            ]);
                        @endphp
                        <tr wire:key="caso-{{ $caso->id }}" onclick="window.Livewire.navigate('{{ $url }}')" style="cursor:pointer;">
                            <td>
                                <x-ui.badge :tone="$tipoTone" size="sm">
                                    {{ ucfirst(str_replace('_', ' ', $caso->tipo_caso)) }}
                                </x-ui.badge>
                            </td>
                            <td><span style="font-weight:500;">{{ $nombre !== '' ? $nombre : '—' }}</span></td>
                            <td><span class="font-mono" style="font-size:12px;">{{ $caso->identificacion }}</span></td>
                            <td style="font-size:12px;color:var(--text-secondary);">{{ $caso->cartera_nombre ?? '—' }}</td>
                            <td style="font-size:12px;color:var(--text-secondary);">{{ $caso->estado_caso_nombre ?? '—' }}</td>
                            <td class="num">{{ $caso->prioridad }}</td>
                            <td>
                                @if($caso->tiene_compromiso_vigente)
                                    <x-ui.badge tone="success" size="sm">{{ __('casos.commitment_active') }}</x-ui.badge>
                                @else
                                    <span style="font-size:11px;color:var(--text-tertiary);">—</span>
                                @endif
                            </td>
                            <td><x-ui.icon name="chevron-right" :size="14" style="color:var(--text-muted);" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div style="padding:10px 16px;border-top:1px solid var(--border);">
                {{ $casos->links() }}
            </div>
        @endif
    </div>
</div>
