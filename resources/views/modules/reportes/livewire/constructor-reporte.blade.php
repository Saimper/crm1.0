<div class="constructor-reporte page" style="display:grid;grid-template-columns:280px 1fr;gap:24px;">
    <aside class="card card-pad-sm" style="padding:12px;background:var(--bg-subtle);">
        <h3 class="card-title" style="margin-bottom:8px;">{{ __('reportes.available_fields') }}</h3>

        <label class="field-label">{{ __('reportes.root_entity') }}</label>
        <select wire:model.live="entidadRaiz" class="select input-sm" style="margin-bottom:12px;">
            <option value="casos">{{ __('reportes.entity_casos') }}</option>
            <option value="gestiones">{{ __('reportes.entity_gestiones') }}</option>
            <option value="compromisos">{{ __('reportes.entity_compromisos') }}</option>
            <option value="personas">{{ __('reportes.entity_personas') }}</option>
        </select>

        <input wire:model.live.debounce.300ms="busquedaCampo"
               type="search" placeholder="{{ __('reportes.search_field') }}"
               class="input input-sm" style="margin-bottom:8px;">

        <div style="max-height:480px;overflow-y:auto;">
            @foreach($this->camposDisponibles as $clave => $info)
                <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 4px;border-bottom:1px solid var(--border);">
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:13px;font-weight:500;">{{ $info['etiqueta'] }}</div>
                        <div class="mono" style="font-size:10px;color:var(--text-tertiary);">{{ $clave }} · {{ $info['tipo'] }}</div>
                    </div>
                    <div style="display:flex;gap:4px;">
                        <button type="button" title="{{ __('reportes.add_column') }}" wire:click="agregarColumna(@js($clave))" class="btn btn-secondary btn-sm" style="padding:2px 6px;font-size:11px;height:auto;">+col</button>
                        <button type="button" title="{{ __('reportes.add_filter') }}" wire:click="agregarFiltro(@js($clave))" class="btn btn-secondary btn-sm" style="padding:2px 6px;font-size:11px;height:auto;">+filtro</button>
                    </div>
                </div>
            @endforeach
        </div>
    </aside>

    <main style="display:flex;flex-direction:column;gap:16px;">
        <section class="card card-pad">
            <h3 class="card-title" style="margin-bottom:8px;">{{ __('reportes.section_definition') }}</h3>
            <div style="display:grid;grid-template-columns:1fr 2fr;gap:12px;">
                <div>
                    <label class="field-label">{{ __('reportes.label_code') }}</label>
                    <input wire:model="codigo" class="input">
                </div>
                <div>
                    <label class="field-label">{{ __('reportes.label_name') }}</label>
                    <input wire:model="nombre" class="input">
                </div>
            </div>
            <div style="margin-top:8px;">
                <label class="field-label">{{ __('reportes.label_description') }}</label>
                <textarea wire:model="descripcion" rows="2" class="textarea"></textarea>
            </div>
        </section>

        <section class="card card-pad">
            <h3 class="card-title" style="margin-bottom:8px;">{{ __('reportes.section_columns', ['count' => count($columnas)]) }}</h3>
            @if(count($columnas) === 0)
                <p style="color:var(--text-muted);font-size:13px;">{{ __('reportes.empty_columns') }}</p>
            @else
                <table class="table table-compact">
                    <thead>
                        <tr>
                            <th>{{ __('reportes.col_field') }}</th>
                            <th>{{ __('reportes.col_label') }}</th>
                            <th>{{ __('reportes.col_aggregation') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($columnas as $i => $col)
                            <tr>
                                <td><span class="mono" style="font-size:11px;">{{ $col['campo'] }}</span></td>
                                <td><input wire:model="columnas.{{ $i }}.etiqueta" class="input input-sm"></td>
                                <td>
                                    <select wire:change="setAgregacion({{ $i }}, $event.target.value)" class="select input-sm">
                                        <option value="" @selected($col['agregacion'] === null)>—</option>
                                        @foreach(['count','sum','avg','min','max'] as $a)
                                            <option value="{{ $a }}" @selected($col['agregacion'] === $a)>{{ strtoupper($a) }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><button type="button" wire:click="quitarColumna({{ $i }})" class="btn btn-ghost btn-sm" style="color:var(--danger);">×</button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>

        <section class="card card-pad">
            <h3 class="card-title" style="margin-bottom:8px;">{{ __('reportes.section_filters', ['count' => count($filtros)]) }}</h3>
            @foreach($filtros as $i => $f)
                <div style="display:grid;grid-template-columns:2fr 1fr 2fr auto;gap:8px;align-items:center;margin-bottom:6px;font-size:13px;">
                    <div class="mono" style="font-size:11px;">{{ $f['campo'] }}</div>
                    <select wire:model="filtros.{{ $i }}.operador" class="select input-sm">
                        @foreach(['igual','distinto','mayor','menor','contiene','empieza','termina','vacio','no_vacio'] as $op)
                            <option value="{{ $op }}">{{ $op }}</option>
                        @endforeach
                    </select>
                    <input wire:model="filtros.{{ $i }}.valor" class="input input-sm">
                    <button type="button" wire:click="quitarFiltro({{ $i }})" class="btn btn-ghost btn-sm" style="color:var(--danger);">×</button>
                </div>
            @endforeach
        </section>

        <section class="card card-pad">
            <h3 class="card-title" style="margin-bottom:8px;">{{ __('reportes.section_groupings') }}</h3>
            <div style="display:flex;flex-wrap:wrap;gap:6px;">
                @foreach($agrupaciones as $i => $g)
                    <span class="chip active">
                        {{ $g }}
                        <button type="button" wire:click="quitarAgrupacion({{ $i }})" style="background:none;border:none;cursor:pointer;color:inherit;margin-left:4px;">×</button>
                    </span>
                @endforeach
            </div>
            <div style="margin-top:8px;">
                <select wire:change="agregarAgrupacion($event.target.value); $event.target.value=''" class="select input-sm">
                    <option value="">{{ __('reportes.add_grouping') }}</option>
                    @foreach($this->camposDisponibles as $clave => $info)
                        <option value="{{ $clave }}">{{ $info['etiqueta'] }}</option>
                    @endforeach
                </select>
            </div>
        </section>

        <section class="card card-pad">
            <h3 class="card-title" style="margin-bottom:8px;">{{ __('reportes.section_order') }}</h3>
            @foreach($orden as $i => $o)
                <div style="display:flex;gap:8px;align-items:center;font-size:13px;margin-bottom:4px;">
                    <span class="mono" style="font-size:11px;">{{ $o['campo'] }}</span>
                    <select wire:model="orden.{{ $i }}.direccion" class="select input-sm">
                        <option value="asc">ASC</option>
                        <option value="desc">DESC</option>
                    </select>
                    <button type="button" wire:click="quitarOrden({{ $i }})" class="btn btn-ghost btn-sm" style="color:var(--danger);">×</button>
                </div>
            @endforeach
            <div style="margin-top:8px;">
                <select wire:change="agregarOrden($event.target.value, 'asc'); $event.target.value=''" class="select input-sm">
                    <option value="">{{ __('reportes.add_order') }}</option>
                    @foreach($this->camposDisponibles as $clave => $info)
                        <option value="{{ $clave }}">{{ $info['etiqueta'] }}</option>
                    @endforeach
                </select>
            </div>
        </section>

        @if($errorGuardar !== null)
            <div class="alert alert-danger">{{ $errorGuardar }}</div>
        @endif

        <div style="display:flex;gap:8px;">
            <button type="button" wire:click="preview" class="btn btn-secondary">{{ __('reportes.btn_preview') }}</button>
            <button type="button" wire:click="guardar" class="btn btn-primary">{{ __('reportes.btn_save_definition') }}</button>
        </div>

        @if($previewFilas !== null && $previewCabeceras !== null)
            <section class="card card-pad">
                <h3 class="card-title" style="margin-bottom:8px;">{{ __('reportes.section_preview') }}</h3>
                <div style="overflow-x:auto;">
                    <table class="table table-compact">
                        <thead>
                            <tr>
                                @foreach($previewCabeceras as $h)
                                    <th>{{ $h['etiqueta'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($previewFilas as $fila)
                                <tr>
                                    @foreach($previewCabeceras as $j => $h)
                                        <td>{{ $fila['col_'.$j] ?? '' }}</td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr><td colspan="{{ count($previewCabeceras) }}" style="padding:12px;text-align:center;color:var(--text-muted);">{{ __('reportes.empty_preview') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </main>
</div>
