<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">{{ __('personas.title_list') }}</h1>
            <div class="page-subtitle">{{ __('personas.subtitle_registered', ['count' => $totalProyecto]) }}</div>
        </div>
        <div style="display:flex;gap:8px;">
            @can('personas.crear', app('tenancy.proyecto_activo')->id)
                <a href="{{ route('proyectos.personas.crear', ['proyecto_id' => app('tenancy.proyecto_activo')->id]) }}"
                   wire:navigate class="btn btn-primary">
                    <x-ui.icon name="plus" :size="14" />
                    {{ __('personas.new_person') }}
                </a>
            @endcan
        </div>
    </div>

    <div class="card" style="padding:0;">
        <div style="padding:12px 16px;border-bottom:1px solid var(--border);display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <div style="position:relative;width:300px;">
                <span style="position:absolute;left:9px;top:11px;color:var(--text-muted);pointer-events:none;">
                    <x-ui.icon name="search" :size="13" />
                </span>
                <input type="text" wire:model.live.debounce.300ms="busqueda"
                       class="input" placeholder="{{ __('personas.search_placeholder') }}" style="padding-left:28px;"/>
            </div>
            <select wire:model.live="tipoPersona" class="input" style="width:160px;">
                <option value="">{{ __('personas.all_types') }}</option>
                <option value="fisica">{{ __('personas.type_physical') }}</option>
                <option value="juridica">{{ __('personas.type_legal') }}</option>
            </select>
            @if($busqueda !== '' || $tipoPersona !== '')
                <button type="button" wire:click="limpiarFiltros" class="btn btn-ghost btn-sm">{{ __('personas.clear_filters') }}</button>
            @endif
            <span style="flex:1;"></span>
            <span style="font-size:12px;color:var(--text-tertiary);">{{ __('personas.results', ['count' => $personas->total()]) }}</span>
        </div>

        @if($personas->isEmpty())
            <div class="empty">
                <div class="empty-icon"><x-ui.icon name="user" :size="32" /></div>
                <div class="empty-title">{{ __('personas.empty_title') }}</div>
                <div class="empty-desc">
                    @if($busqueda !== '' || $tipoPersona !== '')
                        {{ __('personas.empty_with_filters') }}
                    @else
                        {{ __('personas.empty_no_filters') }}
                    @endif
                </div>
            </div>
        @else
            <table class="table table-compact table-clickable">
                <thead>
                    <tr>
                        <th style="width:80px;">{{ __('personas.col_type') }}</th>
                        <th style="width:170px;">{{ __('personas.col_id_doc') }}</th>
                        <th>{{ __('personas.col_name') }}</th>
                        <th class="num" style="width:80px;">{{ __('personas.col_cases') }}</th>
                        <th style="width:130px;">{{ __('personas.col_created') }}</th>
                        <th style="width:60px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($personas as $p)
                        @php
                            $nombre = $p->tipo_persona === 'juridica'
                                ? ($p->razon_social ?? '—')
                                : trim(($p->nombres ?? '').' '.($p->apellidos ?? ''));
                            $url = route('proyectos.trabajo', [
                                'proyecto_id' => app('tenancy.proyecto_activo')->id,
                                'persona' => $p->public_id,
                            ]);
                        @endphp
                        <tr wire:key="persona-{{ $p->id }}" onclick="window.Livewire.navigate('{{ $url }}')" style="cursor:pointer;">
                            <td>
                                <x-ui.badge :tone="$p->tipo_persona === 'juridica' ? 'info' : 'neutral'" size="sm">
                                    {{ ucfirst($p->tipo_persona) }}
                                </x-ui.badge>
                            </td>
                            <td>
                                <span class="font-mono" style="font-size:12px;">
                                    {{ $p->tipo_identificacion_codigo ?? '' }}
                                    {{ $p->identificacion }}
                                </span>
                            </td>
                            <td><span style="font-weight:500;">{{ $nombre !== '' ? $nombre : '—' }}</span></td>
                            <td class="num">{{ $p->total_casos }}</td>
                            <td style="font-size:12px;color:var(--text-secondary);">
                                {{ \Illuminate\Support\Carbon::parse($p->creada_en)->format('d/m/Y') }}
                            </td>
                            <td><x-ui.icon name="chevron-right" :size="14" style="color:var(--text-muted);" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div style="padding:10px 16px;border-top:1px solid var(--border);">
                {{ $personas->links() }}
            </div>
        @endif
    </div>
</div>
