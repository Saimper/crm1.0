<div class="space-y-4">
    @if(session('admin-campos-ok'))
        <div class="rounded border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
            {{ session('admin-campos-ok') }}
        </div>
    @endif

    <div class="flex items-center justify-between gap-3">
        <div>
            <label class="block text-xs font-medium text-gray-700">Proyecto</label>
            <select wire:model.live="proyectoSeleccionadoId"
                    class="mt-1 block w-72 text-sm rounded border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                @foreach($proyectos as $p)
                    <option value="{{ $p->id }}">
                        {{ $p->codigo }} · {{ $p->nombre }} ({{ $p->tipo_operacion }})
                    </option>
                @endforeach
            </select>
        </div>
        <button type="button" wire:click="abrirFormCrear"
                class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">
            Nuevo campo
        </button>
    </div>

    <div class="rounded-md border border-gray-200 bg-white overflow-hidden">
        @if($campos->isEmpty())
            <div class="p-6 text-sm text-gray-500 text-center">
                Este proyecto aún no tiene campos personalizados.
            </div>
        @else
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-600">
                    <tr>
                        <th class="px-3 py-2 text-left">Ámbito</th>
                        <th class="px-3 py-2 text-left">Código</th>
                        <th class="px-3 py-2 text-left">Etiqueta</th>
                        <th class="px-3 py-2 text-left">Tipo</th>
                        <th class="px-3 py-2 text-left">Obl.</th>
                        <th class="px-3 py-2 text-left">Orden</th>
                        <th class="px-3 py-2 text-left">Estado</th>
                        <th class="px-3 py-2 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($campos as $c)
                        <tr>
                            <td class="px-3 py-2">
                                <span class="text-xs uppercase text-gray-500">{{ $c->ambito }}</span>
                                <div class="text-gray-800">
                                    @if($c->ambito === 'caso')
                                        {{ $c->cartera_nombre ?? '—' }}
                                    @elseif($c->ambito === 'gestion')
                                        {{ $c->tipo_gestion_nombre ?? '—' }}
                                    @else
                                        #{{ $c->ambito_id }}
                                    @endif
                                </div>
                            </td>
                            <td class="px-3 py-2 font-mono text-xs">{{ $c->codigo }}</td>
                            <td class="px-3 py-2">{{ $c->etiqueta }}</td>
                            <td class="px-3 py-2 text-xs text-gray-600">{{ $c->tipo }}</td>
                            <td class="px-3 py-2">
                                @if($c->obligatorio)<span class="text-red-700 text-xs font-semibold">sí</span>@else<span class="text-gray-400 text-xs">no</span>@endif
                            </td>
                            <td class="px-3 py-2 text-xs">{{ $c->orden }}</td>
                            <td class="px-3 py-2">
                                @if($c->activo)
                                    <span class="inline-block rounded px-2 py-0.5 text-xs bg-emerald-100 text-emerald-800">activo</span>
                                @else
                                    <span class="inline-block rounded px-2 py-0.5 text-xs bg-gray-100 text-gray-600">inactivo</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right">
                                <button type="button" wire:click="abrirFormEditar({{ $c->id }})"
                                        class="text-xs text-indigo-700 hover:underline">Editar</button>
                                @if($c->activo)
                                    <button type="button" wire:click="desactivar({{ $c->id }})"
                                            wire:confirm="¿Desactivar este campo?"
                                            class="ml-2 text-xs text-red-700 hover:underline">Desactivar</button>
                                @else
                                    <button type="button" wire:click="activar({{ $c->id }})"
                                            class="ml-2 text-xs text-emerald-700 hover:underline">Activar</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @if($formVisible)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/40"
             wire:key="form-campo-personalizado">
            <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl p-6 space-y-3">
                <div class="text-lg font-semibold text-gray-900">
                    {{ $campoEditandoId === null ? 'Nuevo campo personalizado' : 'Editar campo personalizado' }}
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Proyecto</label>
                        <select wire:model.live="form.proyecto_id"
                                class="mt-1 block w-full text-sm rounded border-gray-300">
                            @foreach($proyectos as $p)
                                <option value="{{ $p->id }}">{{ $p->codigo }} — {{ $p->nombre }}</option>
                            @endforeach
                        </select>
                        @error('form.proyecto_id')<div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Ámbito</label>
                        <select wire:model.live="form.ambito"
                                class="mt-1 block w-full text-sm rounded border-gray-300">
                            <option value="caso">Caso × Cartera</option>
                            <option value="gestion">Gestión × Tipo de gestión</option>
                        </select>
                        @error('form.ambito')<div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>@enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-gray-700">
                            {{ $form['ambito'] === 'caso' ? 'Cartera' : 'Tipo de gestión' }}
                        </label>
                        <select wire:model="form.ambito_id"
                                class="mt-1 block w-full text-sm rounded border-gray-300">
                            <option value="">—</option>
                            @if($form['ambito'] === 'caso')
                                @foreach($carteras as $ca)
                                    <option value="{{ $ca->id }}">{{ $ca->codigo }} — {{ $ca->nombre }}</option>
                                @endforeach
                            @else
                                @foreach($tiposGestion as $tg)
                                    <option value="{{ $tg->id }}">{{ $tg->codigo }} — {{ $tg->nombre }}</option>
                                @endforeach
                            @endif
                        </select>
                        @error('form.ambito_id')<div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>@enderror
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700">Código (snake_case)</label>
                        <input type="text" wire:model="form.codigo" placeholder="p.ej. operador_externo"
                               class="mt-1 block w-full text-sm rounded border-gray-300 font-mono"/>
                        @error('form.codigo')<div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Etiqueta visible</label>
                        <input type="text" wire:model="form.etiqueta"
                               class="mt-1 block w-full text-sm rounded border-gray-300"/>
                        @error('form.etiqueta')<div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>@enderror
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700">Tipo</label>
                        <select wire:model="form.tipo"
                                class="mt-1 block w-full text-sm rounded border-gray-300">
                            @foreach($tiposCampo as $t)
                                <option value="{{ $t['valor'] }}">{{ $t['etiqueta'] }}</option>
                            @endforeach
                        </select>
                        @error('form.tipo')<div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Orden</label>
                        <input type="number" min="0" wire:model="form.orden"
                               class="mt-1 block w-full text-sm rounded border-gray-300"/>
                        @error('form.orden')<div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>@enderror
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700">Longitud máxima (opcional)</label>
                        <input type="number" min="1" wire:model="form.longitud_max"
                               class="mt-1 block w-full text-sm rounded border-gray-300"/>
                        @error('form.longitud_max')<div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>@enderror
                    </div>
                    <div class="flex items-end gap-4">
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" wire:model="form.obligatorio" class="rounded"/>
                            <span>Obligatorio</span>
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" wire:model="form.activo" class="rounded"/>
                            <span>Activo</span>
                        </label>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" wire:click="cerrarForm"
                            class="px-3 py-1.5 text-xs text-gray-700 border border-gray-300 rounded hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button type="button" wire:click="guardar"
                            class="px-3 py-1.5 text-xs text-white bg-indigo-600 rounded hover:bg-indigo-700">
                        Guardar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
