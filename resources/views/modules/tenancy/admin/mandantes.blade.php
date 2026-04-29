<div class="space-y-4">
    @if(session('admin-mandantes-ok'))
        <div class="rounded border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
            {{ session('admin-mandantes-ok') }}
        </div>
    @endif

    <div class="flex items-center justify-between">
        <div class="text-xs text-gray-500">
            Total: <span class="font-semibold text-gray-800">{{ $mandantes->count() }}</span>
        </div>
        <button type="button" wire:click="abrirFormCrear"
                class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">
            Nuevo mandante
        </button>
    </div>

    <div class="rounded-md border border-gray-200 bg-white overflow-hidden">
        @if($mandantes->isEmpty())
            <div class="p-6 text-sm text-gray-500 text-center">Sin mandantes registrados.</div>
        @else
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-600">
                    <tr>
                        <th class="px-3 py-2 text-left">Código</th>
                        <th class="px-3 py-2 text-left">Nombre</th>
                        <th class="px-3 py-2 text-left">Documento</th>
                        <th class="px-3 py-2 text-right">Proyectos</th>
                        <th class="px-3 py-2 text-left">Estado</th>
                        <th class="px-3 py-2 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($mandantes as $m)
                        <tr>
                            <td class="px-3 py-2 font-mono text-xs">{{ $m->codigo }}</td>
                            <td class="px-3 py-2">{{ $m->nombre }}</td>
                            <td class="px-3 py-2 text-xs text-gray-600">{{ $m->documento ?? '—' }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ $m->total_proyectos }}</td>
                            <td class="px-3 py-2">
                                @if($m->activo)
                                    <span class="inline-block rounded px-2 py-0.5 text-xs bg-emerald-100 text-emerald-800">activo</span>
                                @else
                                    <span class="inline-block rounded px-2 py-0.5 text-xs bg-gray-100 text-gray-600">inactivo</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right">
                                <button type="button" wire:click="abrirFormEditar({{ $m->id }})"
                                        class="text-xs text-indigo-700 hover:underline">Editar</button>
                                @if($m->activo)
                                    <button type="button" wire:click="desactivar({{ $m->id }})"
                                            wire:confirm="¿Desactivar este mandante? Los proyectos existentes no se afectan."
                                            class="ml-2 text-xs text-red-700 hover:underline">Desactivar</button>
                                @else
                                    <button type="button" wire:click="activar({{ $m->id }})"
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
             wire:key="form-mandante">
            <div class="bg-white rounded-lg shadow-lg w-full max-w-lg p-6 space-y-3">
                <div class="text-lg font-semibold text-gray-900">
                    {{ $editandoId === null ? 'Nuevo mandante' : 'Editar mandante' }}
                </div>

                <div class="space-y-3 text-sm">
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Código (A-Z, 0-9, _)</label>
                        <input type="text" wire:model="form.codigo" placeholder="BANCO_X"
                               class="mt-1 block w-full text-sm rounded border-gray-300 font-mono uppercase"/>
                        @error('form.codigo')<div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Nombre comercial</label>
                        <input type="text" wire:model="form.nombre"
                               class="mt-1 block w-full text-sm rounded border-gray-300"/>
                        @error('form.nombre')<div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Documento fiscal (opcional)</label>
                        <input type="text" wire:model="form.documento"
                               class="mt-1 block w-full text-sm rounded border-gray-300"/>
                        @error('form.documento')<div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>@enderror
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
