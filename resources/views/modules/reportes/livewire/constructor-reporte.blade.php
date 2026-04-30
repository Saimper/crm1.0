<div class="constructor-reporte" style="display:grid;grid-template-columns:280px 1fr;gap:24px;">
    <aside style="border:1px solid #e5e7eb;border-radius:8px;padding:12px;background:#fafafa;">
        <h3 style="font-weight:600;margin-bottom:8px;">Campos disponibles</h3>

        <label style="display:block;font-size:12px;margin-bottom:4px;">Entidad raíz</label>
        <select wire:model.live="entidadRaiz" style="width:100%;padding:6px;border:1px solid #d1d5db;border-radius:6px;margin-bottom:12px;">
            <option value="casos">Casos</option>
            <option value="gestiones">Gestiones</option>
            <option value="compromisos">Compromisos</option>
            <option value="personas">Personas</option>
        </select>

        <input wire:model.live.debounce.300ms="busquedaCampo"
               type="search" placeholder="Buscar campo..."
               style="width:100%;padding:6px;border:1px solid #d1d5db;border-radius:6px;margin-bottom:8px;">

        <div style="max-height:480px;overflow-y:auto;">
            @foreach($this->camposDisponibles as $clave => $info)
                <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 4px;border-bottom:1px solid #f0f0f0;">
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:13px;font-weight:500;truncate;">{{ $info['etiqueta'] }}</div>
                        <div style="font-size:10px;color:#6b7280;">{{ $clave }} · {{ $info['tipo'] }}</div>
                    </div>
                    <div style="display:flex;gap:4px;">
                        <button type="button" title="Agregar columna" wire:click="agregarColumna(@js($clave))" style="padding:2px 6px;border:1px solid #d1d5db;border-radius:4px;background:white;cursor:pointer;font-size:11px;">+col</button>
                        <button type="button" title="Agregar filtro" wire:click="agregarFiltro(@js($clave))" style="padding:2px 6px;border:1px solid #d1d5db;border-radius:4px;background:white;cursor:pointer;font-size:11px;">+filtro</button>
                    </div>
                </div>
            @endforeach
        </div>
    </aside>

    <main style="display:flex;flex-direction:column;gap:16px;">
        <section style="border:1px solid #e5e7eb;border-radius:8px;padding:16px;">
            <h3 style="font-weight:600;margin-bottom:8px;">Definición</h3>
            <div style="display:grid;grid-template-columns:1fr 2fr;gap:12px;">
                <div>
                    <label style="display:block;font-size:12px;margin-bottom:4px;">Código *</label>
                    <input wire:model="codigo" style="width:100%;padding:6px;border:1px solid #d1d5db;border-radius:6px;">
                </div>
                <div>
                    <label style="display:block;font-size:12px;margin-bottom:4px;">Nombre *</label>
                    <input wire:model="nombre" style="width:100%;padding:6px;border:1px solid #d1d5db;border-radius:6px;">
                </div>
            </div>
            <div style="margin-top:8px;">
                <label style="display:block;font-size:12px;margin-bottom:4px;">Descripción</label>
                <textarea wire:model="descripcion" rows="2" style="width:100%;padding:6px;border:1px solid #d1d5db;border-radius:6px;"></textarea>
            </div>
        </section>

        <section style="border:1px solid #e5e7eb;border-radius:8px;padding:16px;">
            <h3 style="font-weight:600;margin-bottom:8px;">Columnas ({{ count($columnas) }})</h3>
            @if(count($columnas) === 0)
                <p style="color:#9ca3af;font-size:13px;">Sin columnas. Agrega desde el panel izquierdo.</p>
            @else
                <table style="width:100%;font-size:13px;">
                    <thead>
                        <tr style="border-bottom:1px solid #e5e7eb;text-align:left;">
                            <th style="padding:6px 4px;">Campo</th>
                            <th style="padding:6px 4px;">Etiqueta</th>
                            <th style="padding:6px 4px;">Agregación</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($columnas as $i => $col)
                            <tr style="border-bottom:1px solid #f3f4f6;">
                                <td style="padding:6px 4px;font-family:monospace;font-size:11px;">{{ $col['campo'] }}</td>
                                <td style="padding:6px 4px;"><input wire:model="columnas.{{ $i }}.etiqueta" style="width:100%;padding:4px;border:1px solid #d1d5db;border-radius:4px;"></td>
                                <td style="padding:6px 4px;">
                                    <select wire:change="setAgregacion({{ $i }}, $event.target.value)" style="padding:4px;border:1px solid #d1d5db;border-radius:4px;">
                                        <option value="" @selected($col['agregacion'] === null)>—</option>
                                        @foreach(['count','sum','avg','min','max'] as $a)
                                            <option value="{{ $a }}" @selected($col['agregacion'] === $a)>{{ strtoupper($a) }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><button type="button" wire:click="quitarColumna({{ $i }})" style="color:#dc2626;border:none;background:none;cursor:pointer;">×</button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>

        <section style="border:1px solid #e5e7eb;border-radius:8px;padding:16px;">
            <h3 style="font-weight:600;margin-bottom:8px;">Filtros ({{ count($filtros) }})</h3>
            @foreach($filtros as $i => $f)
                <div style="display:grid;grid-template-columns:2fr 1fr 2fr auto;gap:8px;align-items:center;margin-bottom:6px;font-size:13px;">
                    <div style="font-family:monospace;font-size:11px;">{{ $f['campo'] }}</div>
                    <select wire:model="filtros.{{ $i }}.operador" style="padding:4px;border:1px solid #d1d5db;border-radius:4px;">
                        @foreach(['igual','distinto','mayor','menor','contiene','empieza','termina','vacio','no_vacio'] as $op)
                            <option value="{{ $op }}">{{ $op }}</option>
                        @endforeach
                    </select>
                    <input wire:model="filtros.{{ $i }}.valor" style="padding:4px;border:1px solid #d1d5db;border-radius:4px;">
                    <button type="button" wire:click="quitarFiltro({{ $i }})" style="color:#dc2626;border:none;background:none;cursor:pointer;">×</button>
                </div>
            @endforeach
        </section>

        <section style="border:1px solid #e5e7eb;border-radius:8px;padding:16px;">
            <h3 style="font-weight:600;margin-bottom:8px;">Agrupaciones</h3>
            <div style="display:flex;flex-wrap:wrap;gap:6px;">
                @foreach($agrupaciones as $i => $g)
                    <span style="background:#dbeafe;padding:4px 8px;border-radius:12px;font-size:12px;">
                        {{ $g }}
                        <button type="button" wire:click="quitarAgrupacion({{ $i }})" style="color:#dc2626;border:none;background:none;cursor:pointer;margin-left:4px;">×</button>
                    </span>
                @endforeach
            </div>
            <div style="margin-top:8px;">
                <select wire:change="agregarAgrupacion($event.target.value); $event.target.value=''" style="padding:6px;border:1px solid #d1d5db;border-radius:6px;font-size:12px;">
                    <option value="">+ Agregar agrupación...</option>
                    @foreach($this->camposDisponibles as $clave => $info)
                        <option value="{{ $clave }}">{{ $info['etiqueta'] }}</option>
                    @endforeach
                </select>
            </div>
        </section>

        <section style="border:1px solid #e5e7eb;border-radius:8px;padding:16px;">
            <h3 style="font-weight:600;margin-bottom:8px;">Orden</h3>
            @foreach($orden as $i => $o)
                <div style="display:flex;gap:8px;align-items:center;font-size:13px;margin-bottom:4px;">
                    <span style="font-family:monospace;font-size:11px;">{{ $o['campo'] }}</span>
                    <select wire:model="orden.{{ $i }}.direccion" style="padding:4px;border:1px solid #d1d5db;border-radius:4px;">
                        <option value="asc">ASC</option>
                        <option value="desc">DESC</option>
                    </select>
                    <button type="button" wire:click="quitarOrden({{ $i }})" style="color:#dc2626;border:none;background:none;cursor:pointer;">×</button>
                </div>
            @endforeach
            <div style="margin-top:8px;">
                <select wire:change="agregarOrden($event.target.value, 'asc'); $event.target.value=''" style="padding:6px;border:1px solid #d1d5db;border-radius:6px;font-size:12px;">
                    <option value="">+ Agregar orden...</option>
                    @foreach($this->camposDisponibles as $clave => $info)
                        <option value="{{ $clave }}">{{ $info['etiqueta'] }}</option>
                    @endforeach
                </select>
            </div>
        </section>

        @if($errorGuardar !== null)
            <div style="background:#fee2e2;border:1px solid #f87171;color:#991b1b;padding:8px 12px;border-radius:6px;font-size:13px;">
                {{ $errorGuardar }}
            </div>
        @endif

        <div style="display:flex;gap:8px;">
            <button type="button" wire:click="preview" style="padding:8px 16px;background:#374151;color:white;border:none;border-radius:6px;cursor:pointer;">Vista previa (50 filas)</button>
            <button type="button" wire:click="guardar" style="padding:8px 16px;background:#2563eb;color:white;border:none;border-radius:6px;cursor:pointer;">Guardar definición</button>
        </div>

        @if($previewFilas !== null && $previewCabeceras !== null)
            <section style="border:1px solid #e5e7eb;border-radius:8px;padding:16px;">
                <h3 style="font-weight:600;margin-bottom:8px;">Vista previa</h3>
                <div style="overflow-x:auto;">
                    <table style="width:100%;font-size:12px;border-collapse:collapse;">
                        <thead>
                            <tr style="background:#f9fafb;">
                                @foreach($previewCabeceras as $h)
                                    <th style="padding:6px 8px;text-align:left;border-bottom:1px solid #e5e7eb;">{{ $h['etiqueta'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($previewFilas as $fila)
                                <tr style="border-bottom:1px solid #f3f4f6;">
                                    @foreach($previewCabeceras as $j => $h)
                                        <td style="padding:6px 8px;">{{ $fila['col_'.$j] ?? '' }}</td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr><td colspan="{{ count($previewCabeceras) }}" style="padding:12px;text-align:center;color:#9ca3af;">Sin filas.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </main>
</div>
