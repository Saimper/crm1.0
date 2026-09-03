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

            <div style="position:relative;">
                <button type="button" wire:click="alternarSelectorColumnas"
                        class="btn btn-ghost btn-sm" title="{{ __('casos.columns_title') }}">
                    <x-ui.icon name="settings" :size="13" /> {{ __('casos.columns') }}
                </button>

                @if($selectorColumnasAbierto)
                    <div style="position:absolute;right:0;top:34px;z-index:60;width:290px;background:var(--bg-elev);
                                border:1px solid var(--border);border-radius:10px;
                                box-shadow:0 10px 28px rgba(15,23,42,.18);padding:12px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                            <strong style="font-size:12px;">{{ __('casos.columns_title') }}</strong>
                            <button type="button" wire:click="restaurarColumnas"
                                    class="btn btn-ghost btn-sm" style="font-size:11px;">{{ __('casos.columns_reset') }}</button>
                        </div>

                        <div style="max-height:320px;overflow-y:auto;display:flex;flex-direction:column;gap:2px;">
                            @foreach($catalogoColumnas as $columna)
                                @php
                                    $activa = in_array($columna->clave, $columnasVisibles, true);
                                    $posicion = array_search($columna->clave, $columnasVisibles, true);
                                @endphp
                                <div style="display:flex;align-items:center;gap:6px;padding:3px 2px;">
                                    {{-- input fuera del label: anidarlo hace que el click burbujee
                                         al label y este lo reenvíe, disparando wire:click dos veces
                                         (la columna se activaba y desactivaba en el mismo clic). --}}
                                    <input type="checkbox" id="col-{{ $columna->clave }}" @checked($activa)
                                           wire:click="alternarColumna('{{ $columna->clave }}')"/>
                                    <label for="col-{{ $columna->clave }}"
                                           style="flex:1;cursor:pointer;font-size:12px;">{{ $columna->etiqueta }}</label>
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
                        @foreach($columnasVisibles as $clave)
                            @php $col = collect($catalogoColumnas)->firstWhere('clave', $clave); @endphp
                            @if($col)
                                <th @class(['num' => $col->numerica])>{{ $col->etiqueta }}</th>
                            @endif
                        @endforeach
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
                        <tr wire:key="caso-{{ $caso->id }}" onclick="window.Livewire.navigate('{{ $url }}')" style="cursor:pointer;">
                            @foreach($columnasVisibles as $clave)
                                @php $col = collect($catalogoColumnas)->firstWhere('clave', $clave); @endphp
                                @if($col)
                                    <x-casos.celda-caso :caso="$caso" :columna="$col" :nombre="$nombre" />
                                @endif
                            @endforeach
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
