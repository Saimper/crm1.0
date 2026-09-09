<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">{{ __('personas.title_list') }}</h1>
            <div class="page-subtitle">{{ __('personas.subtitle_registered', ['count' => $totalProyecto]) }}</div>
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
                               placeholder="{{ __('personas.search_placeholder') }}" />
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
                        <th class="num" style="width:80px;">{{ __('personas.col_cases', ['entidades' => $rotuloCasos]) }}</th>
                        <th style="width:130px;">{{ __('personas.col_created') }}</th>
                        <th style="width:60px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($personas as $p)
                        @php
                            // El nombre se toma de la columna que lo tenga: una persona
                            // marcada como física puede traer razón social (y al revés)
                            // según cómo venga la fuente importada.
                            $nombre = trim(($p->nombres ?? '').' '.($p->apellidos ?? ''));
                            $nombre = $nombre !== '' ? $nombre : trim((string) ($p->razon_social ?? ''));
                            $url = route('proyectos.trabajo', [
                                'proyecto_id' => app('tenancy.proyecto_activo')->id,
                                'persona' => $p->public_id,
                            ]);
                        @endphp
                        <tr wire:key="persona-{{ $p->id }}" onclick="window.Livewire.navigate('{{ $url }}')">
                            <td>
                                <x-ui.badge :tone="$p->tipo_persona === 'juridica' ? 'info' : 'neutral'" size="sm">
                                    {{ ucfirst($p->tipo_persona) }}
                                </x-ui.badge>
                            </td>
                            <td>
                                <span class="font-mono text-sm">
                                    {{ $p->tipo_identificacion_codigo ?? '' }}
                                    {{ $p->identificacion }}
                                </span>
                            </td>
                            <td><span class="font-medium">{{ $nombre !== '' ? $nombre : '—' }}</span></td>
                            <td class="num">{{ $p->total_casos }}</td>
                            <td class="text-sm text-ink-600">
                                {{ hora_local($p->creada_en, 'd/m/Y') }}
                            </td>
                            <td class="text-ink-400"><x-ui.icon name="chevron-right" :size="14" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="card-header" style="border-top:1px solid var(--border);border-bottom:0;">
                {{ $personas->links() }}
            </div>
        @endif
    </div>
</div>
