<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">{{ __('casos.title_list', ['entidades' => $rotuloCasos]) }}</h1>
            <div class="page-subtitle">{{ __('casos.subtitle_open', ['count' => $totalProyecto, 'entidades' => $rotuloCasos]) }}</div>
        </div>
    </div>

    <div class="card">
        <x-ui.toolbar :count="__('casos.results', ['count' => $casos->total()])">
            <x-ui.search-input wire:model.live.debounce.300ms="busqueda"
                               placeholder="{{ __('casos.search_placeholder') }}" />
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
            @if($puedeTomar)
                <label class="flex items-center gap-1.5 whitespace-nowrap text-sm">
                    <input type="checkbox" wire:model.live="soloSinDuenio" />
                    {{ __('casos.assign_only_unowned') }}
                </label>
            @endif
            @if($busqueda !== '' || $carteraId !== '' || $estadoCasoId !== '' || $soloSinDuenio)
                <button type="button" wire:click="limpiarFiltros" class="btn btn-ghost btn-sm">{{ __('casos.clear_filters') }}</button>
            @endif
            @can('casos.exportar', (int) app('tenancy.proyecto_activo')->id)
                {{-- Sin wire:navigate: es una descarga, no una pantalla. El href lleva los filtros puestos. --}}
                <a href="{{ $urlExportar }}" class="btn btn-secondary btn-sm">
                    <x-ui.icon name="download" :size="13" />
                    {{ __('casos.export_csv') }}
                </a>
            @endcan
            <x-slot:acciones>
            <div class="popover-anchor">
                <button type="button" wire:click="alternarSelectorColumnas"
                        class="btn btn-ghost btn-sm" title="{{ __('casos.columns_title') }}">
                    <x-ui.icon name="settings" :size="13" /> {{ __('casos.columns') }}
                </button>

                @if($selectorColumnasAbierto)
                    <div class="popover popover-right" style="width:290px;">
                        <div class="flex items-center justify-between" style="margin-bottom:8px;">
                            <strong class="text-sm">{{ __('casos.columns_title') }}</strong>
                            <button type="button" wire:click="restaurarColumnas"
                                    class="btn btn-ghost btn-sm text-xs">{{ __('casos.columns_reset') }}</button>
                        </div>

                        <div class="popover-scroll flex flex-col" style="gap:2px;">
                            @foreach($catalogoColumnas as $columna)
                                @php
                                    $activa = in_array($columna->clave, $columnasVisibles, true);
                                    $posicion = array_search($columna->clave, $columnasVisibles, true);
                                @endphp
                                <div class="flex items-center" style="gap:6px;padding:3px 2px;">
                                    {{-- input fuera del label: anidarlo hace que el click burbujee
                                         al label y este lo reenvíe, disparando wire:click dos veces
                                         (la columna se activaba y desactivaba en el mismo clic). --}}
                                    <input type="checkbox" id="col-{{ $columna->clave }}" @checked($activa)
                                           wire:click="alternarColumna('{{ $columna->clave }}')"/>
                                    <label for="col-{{ $columna->clave }}"
                                           class="flex-1 min-w-0 text-sm" style="cursor:pointer;">{{ $columna->etiqueta }}</label>
                                    @if($activa)
                                        <button type="button" wire:click="moverColumna('{{ $columna->clave }}', -1)"
                                                class="btn btn-ghost btn-sm" style="padding:1px 5px;"
                                                @disabled($posicion === 0)>&uarr;</button>
                                        <button type="button" wire:click="moverColumna('{{ $columna->clave }}', 1)"
                                                class="btn btn-ghost btn-sm" style="padding:1px 5px;"
                                                @disabled($posicion === count($columnasVisibles) - 1)>&darr;</button>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
            </x-slot:acciones>
        </x-ui.toolbar>

        @if($mensajeAsignacion)
            <x-ui.alert tone="success" class="mx-4 mb-3">{{ $mensajeAsignacion }}</x-ui.alert>
        @endif
        @error('asignacion')
            <x-ui.alert tone="danger" class="mx-4 mb-3">{{ $message }}</x-ui.alert>
        @enderror

        {{-- La lista de antes se queda en pantalla mientras llega la nueva; sólo
             esta barra dice que se está trabajando. --}}
        <x-ui.cargando />

        @if($casos->isEmpty())
            <div class="empty">
                <div class="empty-icon"><x-ui.icon name="folder" :size="32" /></div>
                <div class="empty-title">{{ __('casos.empty_title', ['entidades' => $rotuloCasos]) }}</div>
                <div class="empty-desc">
                    @if($busqueda !== '' || $carteraId !== '' || $estadoCasoId !== '')
                        {{ __('casos.empty_with_filters', ['entidades' => $rotuloCasos]) }}
                    @else
                        {{ __('casos.empty_no_filters', ['entidades' => $rotuloCasos]) }}
                    @endif
                </div>
            </div>
        @else
            <table class="table table-compact table-clickable">
                <thead>
                    <tr>
                        @foreach($columnasVisibles as $clave)
                            @php $col = collect($catalogoColumnas)->firstWhere('clave', $clave); @endphp
                            @if($col)
                                <x-ui.th :num="$col->numerica" :sort="$col->clave" :activo="$orden" :dir="$direccion">
                                    {{ $col->etiqueta }}
                                </x-ui.th>
                            @endif
                        @endforeach
                        @if($puedeTomar)
                            <th class="w-[150px]">{{ __('casos.assign_owner') }}</th>
                        @endif
                        <th style="width:60px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($casos as $caso)
                        @php
                            $nombre = trim(($caso->nombres ?? '').' '.($caso->apellidos ?? ''));
                            $nombre = $nombre !== '' ? $nombre : trim((string) ($caso->razon_social ?? ''));
                            $url = route('proyectos.trabajo', [
                                'proyecto_id' => app('tenancy.proyecto_activo')->id,
                                'persona' => $caso->persona_public_id,
                                'caso' => $caso->public_id,
                            ]);
                        @endphp
                        <tr wire:key="caso-{{ $caso->id }}" onclick="window.Livewire.navigate('{{ $url }}')">
                            @foreach($columnasVisibles as $clave)
                                @php $col = collect($catalogoColumnas)->firstWhere('clave', $clave); @endphp
                                @if($col)
                                    <x-casos.celda-caso :caso="$caso" :columna="$col" :nombre="$nombre" />
                                @endif
                            @endforeach
                            @if($puedeTomar)
                                {{-- El clic de la fila navega a la vista de trabajo; el de este
                                     botón no debe arrastrar con él. --}}
                                <td onclick="event.stopPropagation()">
                                    @if($caso->asignado_a)
                                        <span class="text-sm text-ink-500">{{ $caso->asignado_a }}</span>
                                    @else
                                        <button type="button" class="btn btn-secondary btn-sm"
                                                wire:click="tomarCuenta({{ $caso->id }})"
                                                wire:loading.attr="disabled"
                                                wire:target="tomarCuenta({{ $caso->id }})"
                                                title="{{ __('casos.assign_take_title') }}">
                                            {{ __('casos.assign_take') }}
                                        </button>
                                    @endif
                                </td>
                            @endif
                            <td class="text-ink-400"><x-ui.icon name="chevron-right" :size="14" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="card-footer">
                {{ $casos->links() }}
            </div>
        @endif
    </div>
</div>
