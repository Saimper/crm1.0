<div class="space-y-4">
    @if(session('admin-proyectos-ok'))
        <div class="rounded border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
            {{ session('admin-proyectos-ok') }}
        </div>
    @endif

    <div class="flex items-center justify-between">
        <div class="text-xs text-gray-500">
            Total: <span class="font-semibold text-gray-800">{{ $proyectos->count() }}</span>
        </div>
        <button type="button" wire:click="abrirFormCrear"
                class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">
            Nuevo proyecto
        </button>
    </div>

    <div class="rounded-md border border-gray-200 bg-white overflow-hidden">
        @if($proyectos->isEmpty())
            <div class="p-6 text-sm text-gray-500 text-center">Sin proyectos registrados.</div>
        @else
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-600">
                    <tr>
                        <th class="px-3 py-2 text-left">Mandante</th>
                        <th class="px-3 py-2 text-left">Código</th>
                        <th class="px-3 py-2 text-left">Nombre</th>
                        <th class="px-3 py-2 text-left">Tipo</th>
                        <th class="px-3 py-2 text-left">Vigencia</th>
                        <th class="px-3 py-2 text-right">Carteras</th>
                        <th class="px-3 py-2 text-left">Estado</th>
                        <th class="px-3 py-2 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($proyectos as $p)
                        @php
                            $tipoColor = match ($p->tipo_operacion) {
                                'cobranza' => 'bg-amber-100 text-amber-800',
                                'cx'       => 'bg-sky-100 text-sky-800',
                                'venta'    => 'bg-emerald-100 text-emerald-800',
                                'servicio' => 'bg-violet-100 text-violet-800',
                                default    => 'bg-gray-100 text-gray-700',
                            };
                        @endphp
                        <tr>
                            <td class="px-3 py-2 text-xs">
                                <div class="text-gray-800">{{ $p->mandante_codigo }}</div>
                                <div class="text-[10px] text-gray-500">{{ $p->mandante_nombre }}</div>
                            </td>
                            <td class="px-3 py-2 font-mono text-xs">{{ $p->codigo }}</td>
                            <td class="px-3 py-2">{{ $p->nombre }}</td>
                            <td class="px-3 py-2">
                                <span class="inline-block rounded px-2 py-0.5 text-xs font-medium {{ $tipoColor }}">
                                    {{ $p->tipo_operacion }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-[10px] text-gray-600">
                                {{ $p->fecha_inicio ? \Illuminate\Support\Carbon::parse($p->fecha_inicio)->format('d/m/Y') : '—' }}
                                →
                                {{ $p->fecha_fin ? \Illuminate\Support\Carbon::parse($p->fecha_fin)->format('d/m/Y') : '∞' }}
                            </td>
                            <td class="px-3 py-2 text-right font-mono">{{ $p->total_carteras }}</td>
                            <td class="px-3 py-2">
                                @if($p->activo)
                                    <span class="inline-block rounded px-2 py-0.5 text-xs bg-emerald-100 text-emerald-800">activo</span>
                                @else
                                    <span class="inline-block rounded px-2 py-0.5 text-xs bg-gray-100 text-gray-600">inactivo</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right">
                                <button type="button" wire:click="abrirFormEditar({{ $p->id }})"
                                        class="text-xs text-indigo-700 hover:underline">Editar</button>
                                @if($p->activo)
                                    <button type="button" wire:click="desactivar({{ $p->id }})"
                                            wire:confirm="¿Desactivar este proyecto?"
                                            class="ml-2 text-xs text-red-700 hover:underline">Desactivar</button>
                                @else
                                    <button type="button" wire:click="activar({{ $p->id }})"
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
             wire:key="form-proyecto">
            <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl p-6 space-y-3">
                <div class="text-lg font-semibold text-gray-900">
                    {{ $editandoId === null ? 'Nuevo proyecto' : 'Editar proyecto' }}
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-gray-700">Mandante</label>
                        <select wire:model="form.mandante_id"
                                class="mt-1 block w-full text-sm rounded border-gray-300">
                            <option value="">—</option>
                            @foreach($mandantes as $m)
                                <option value="{{ $m->id }}">{{ $m->codigo }} — {{ $m->nombre }}</option>
                            @endforeach
                        </select>
                        @error('form.mandante_id')<div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Código (A-Z, 0-9, _)</label>
                        <input type="text" wire:model="form.codigo" placeholder="COBRANZA_2026"
                               class="mt-1 block w-full text-sm rounded border-gray-300 font-mono uppercase"/>
                        @error('form.codigo')<div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Nombre</label>
                        <input type="text" wire:model="form.nombre"
                               class="mt-1 block w-full text-sm rounded border-gray-300"/>
                        @error('form.nombre')<div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>@enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-gray-700">Descripción (opcional)</label>
                        <textarea wire:model="form.descripcion" rows="2"
                                  class="mt-1 block w-full text-sm rounded border-gray-300"></textarea>
                        @error('form.descripcion')<div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Tipo de operación</label>
                        <select wire:model="form.tipo_operacion"
                                @disabled($editandoId !== null)
                                class="mt-1 block w-full text-sm rounded border-gray-300 {{ $editandoId !== null ? 'bg-gray-100 cursor-not-allowed' : '' }}">
                            <option value="cobranza">Cobranza</option>
                            <option value="cx">CX / Tickets</option>
                            <option value="venta">Venta</option>
                            <option value="servicio">Servicio técnico</option>
                        </select>
                        @if($editandoId !== null)
                            <div class="text-[10px] text-gray-500 mt-0.5">El tipo de operación no se puede cambiar después de crear el proyecto.</div>
                        @endif
                        @error('form.tipo_operacion')<div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>@enderror
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700">Fecha inicio</label>
                            <input type="date" wire:model="form.fecha_inicio"
                                   class="mt-1 block w-full text-sm rounded border-gray-300"/>
                            @error('form.fecha_inicio')<div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700">Fecha fin</label>
                            <input type="date" wire:model="form.fecha_fin"
                                   class="mt-1 block w-full text-sm rounded border-gray-300"/>
                            @error('form.fecha_fin')<div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>@enderror
                        </div>
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
