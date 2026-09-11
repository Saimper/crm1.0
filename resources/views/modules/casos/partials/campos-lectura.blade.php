@php
    $datosBusqueda = collect($gruposCamposCaso)->flatMap(fn (array $grupo) => collect($grupo['campos'])->map(fn (array $fila) => [
        'id' => $fila['campo']->id,
        'etiqueta' => $fila['campo']->etiqueta,
        'valor' => (string) ($fila['valor'] ?? ''),
    ]))->values()->all();
@endphp

<div class="workspace-fields" wire:key="campos-lectura-{{ $casoActivo->id }}"
     x-data="{
        busqueda: '', mostrarVacios: false, datos: @js($datosBusqueda),
        normalizar(texto) { return texto.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase().trim() },
        coincide(campo) {
            const lleno = campo.valor.trim() !== '' && campo.valor.trim() !== '—';
            return (this.mostrarVacios || lleno) && this.normalizar(campo.etiqueta + ' ' + campo.valor).includes(this.normalizar(this.busqueda));
        },
        get visibles() { return this.datos.filter(campo => this.coincide(campo)).map(campo => campo.id) },
     }">
    <div class="workspace-fields-toolbar">
        <label class="workspace-fields-search">
            <x-ui.icon name="search" :size="16" />
            <input type="search" x-model.debounce.150ms="busqueda" placeholder="Buscar un dato…" aria-label="Buscar en la información del cliente" />
        </label>
        <label class="workspace-fields-toggle">
            <input type="checkbox" x-model="mostrarVacios" /> Mostrar vacíos
        </label>
    </div>
    <p class="workspace-fields-count" role="status" x-text="visibles.length + ' de ' + datos.length + ' datos'"></p>

    @foreach($gruposCamposCaso as $grupo)
        @php $idsGrupo = array_map(fn (array $fila) => $fila['campo']->id, $grupo['campos']); @endphp
        <details open class="workspace-field-group" x-show="@js($idsGrupo).some(id => visibles.includes(id))">
            <summary>
                {{ $grupo['nombre'] === __('casos.fields_ungrouped') ? 'Información del cliente' : $grupo['nombre'] }}
            </summary>
            <div class="workspace-field-list">
                @foreach($grupo['campos'] as $fila)
                    <div wire:key="campo-lectura-{{ $fila['campo']->id }}"
                         x-show="visibles.includes(@js($fila['campo']->id))"
                         @class(['workspace-field-row', 'workspace-field-wide' => mb_strlen((string) ($fila['valor'] ?? '')) > 90])>
                        @if(mb_strlen((string) ($fila['valor'] ?? '')) > 240)
                            <details class="workspace-long-value" :open="busqueda.trim() !== ''">
                                <summary>{{ $fila['campo']->etiqueta }} <span>Ver contenido</span></summary>
                                <x-cp.valor :campo="$fila['campo']" :valor="$fila['valor']" />
                            </details>
                        @else
                            <x-cp.valor :campo="$fila['campo']" :valor="$fila['valor']" />
                        @endif
                    </div>
                @endforeach
            </div>
        </details>
    @endforeach
    <div x-show="visibles.length === 0" x-cloak class="workspace-fields-empty">
        <p x-text="busqueda.trim() ? 'No hay datos que coincidan con la búsqueda.' : 'No hay información adicional del cliente.'"></p>
        <button type="button" class="btn-link" x-on:click="busqueda = ''; mostrarVacios = true">Mostrar toda la información</button>
    </div>
</div>
