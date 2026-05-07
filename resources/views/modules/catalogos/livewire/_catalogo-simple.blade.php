@php
    /** @var \Illuminate\Support\Collection $items */
    /** @var string $titulo */
    /** @var string $singular */
    /** @var \Closure|null $filasExtra    fn(object $item): string  → HTML extra por fila, tras las columnas base */
    /** @var \Closure|null $camposExtra   fn(): string              → HTML extra en el form, antes del orden/activo */
    /** @var \Closure|null $cabecerasExtra fn(): string             → HTML extra en <thead> tras la columna nombre */
    $filasExtra     = $filasExtra     ?? null;
    $camposExtra    = $camposExtra    ?? null;
    $cabecerasExtra = $cabecerasExtra ?? null;
@endphp

<div class="space-y-3">
    @if(session('admin-catalogo-ok'))
        <div class="rounded border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
            {{ session('admin-catalogo-ok') }}
        </div>
    @endif
    @if(session('admin-catalogo-error'))
        <div class="rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
            {{ session('admin-catalogo-error') }}
        </div>
    @endif

    <div class="flex items-center justify-between">
        <div class="text-xs text-gray-500">Total: <span class="font-semibold text-gray-800">{{ $items->count() }}</span></div>
        <button type="button" wire:click="abrirFormCrear"
                class="inline-flex items-center px-3 py-1.5 bg-blue-600 text-white text-xs font-medium rounded hover:bg-blue-700">
            Nuevo {{ $singular }}
        </button>
    </div>

    <div class="rounded-md border border-gray-200 bg-white overflow-hidden">
        @if($items->isEmpty())
            <div class="p-6 text-sm text-gray-500 text-center">Sin registros.</div>
        @else
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-600">
                    <tr>
                        <th class="px-3 py-2 text-left">Código</th>
                        <th class="px-3 py-2 text-left">Nombre</th>
                        {!! $cabecerasExtra ? $cabecerasExtra() : '' !!}
                        <th class="px-3 py-2 text-center">Reordenar</th>
                        <th class="px-3 py-2 text-left">Estado</th>
                        <th class="px-3 py-2 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($items as $i => $it)
                        <tr>
                            <td class="px-3 py-2 font-mono text-xs">{{ $it->codigo }}</td>
                            <td class="px-3 py-2">{{ $it->nombre }}</td>
                            {!! $filasExtra ? $filasExtra($it) : '' !!}
                            <td class="px-3 py-2 text-center text-xs">
                                <button type="button" wire:click="subir({{ $it->id }})"
                                        @disabled($i === 0)
                                        class="px-1.5 py-0.5 rounded border border-gray-300 hover:bg-gray-100 disabled:opacity-30 disabled:cursor-not-allowed"
                                        title="Subir">↑</button>
                                <button type="button" wire:click="bajar({{ $it->id }})"
                                        @disabled($i === $items->count() - 1)
                                        class="px-1.5 py-0.5 rounded border border-gray-300 hover:bg-gray-100 disabled:opacity-30 disabled:cursor-not-allowed ml-1"
                                        title="Bajar">↓</button>
                            </td>
                            <td class="px-3 py-2">
                                @if($it->activo)
                                    <span class="inline-block rounded px-2 py-0.5 text-xs bg-emerald-100 text-emerald-800">activo</span>
                                @else
                                    <span class="inline-block rounded px-2 py-0.5 text-xs bg-gray-100 text-gray-600">inactivo</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right text-xs">
                                <button wire:click="abrirFormEditar({{ $it->id }})" class="text-blue-700 hover:underline">Editar</button>
                                @if($it->activo)
                                    <button wire:click="desactivar({{ $it->id }})" wire:confirm="¿Desactivar?"
                                            class="ml-2 text-red-700 hover:underline">Desactivar</button>
                                @else
                                    <button wire:click="activar({{ $it->id }})" class="ml-2 text-emerald-700 hover:underline">Activar</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @if($formVisible)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/40" wire:key="form-{{ $singular }}">
            <div class="bg-white rounded-lg shadow-lg w-full max-w-lg p-6 space-y-3">
                <div class="text-lg font-semibold text-gray-900">
                    {{ $editandoId === null ? 'Nuevo' : 'Editar' }} {{ $singular }}
                </div>
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Código (A-Z, 0-9, _)</label>
                        <input type="text" wire:model="form.codigo"
                               class="mt-1 block w-full text-sm rounded border-gray-300 font-mono uppercase"/>
                        @error('form.codigo')<div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Nombre visible</label>
                        <input type="text" wire:model="form.nombre"
                               class="mt-1 block w-full text-sm rounded border-gray-300"/>
                        @error('form.nombre')<div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>@enderror
                    </div>

                    {!! $camposExtra ? $camposExtra() : '' !!}

                    <div class="col-span-2 flex items-end">
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="checkbox" wire:model="form.activo" class="rounded"/>
                            <span>Activo</span>
                        </label>
                    </div>
                </div>
                @if($errors->any())
                    <div class="rounded border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-800 space-y-0.5">
                        @foreach($errors->all() as $err)
                            <div>· {{ $err }}</div>
                        @endforeach
                    </div>
                @endif

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button wire:click="cerrarForm" class="px-3 py-1.5 text-xs border border-gray-300 rounded hover:bg-gray-50">Cancelar</button>
                    <button wire:click="guardar" class="px-3 py-1.5 text-xs text-white bg-blue-600 rounded hover:bg-blue-700">Guardar</button>
                </div>
            </div>
        </div>
    @endif
</div>
