<div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">

    @if($mensajeExito)
        <div class="rounded-md bg-emerald-50 border border-emerald-200 px-4 py-2 text-sm text-emerald-800"
             x-data="{}" x-init="setTimeout(() => $wire.set('mensajeExito', null), 3000)">
            {{ $mensajeExito }}
        </div>
    @endif

    <section class="bg-white shadow rounded-lg p-5">
        <div class="text-xs uppercase tracking-wider text-gray-500">Persona</div>
        <h2 class="mt-0.5 text-xl font-bold text-gray-900">{{ $nombre }}</h2>
        <div class="mt-1 text-sm text-gray-600">{{ $persona->identificacion }} · {{ ucfirst($persona->tipo_persona) }}</div>
    </section>

    <section class="bg-white shadow rounded-lg overflow-hidden">
        <header class="px-5 py-3 border-b border-gray-200">
            <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-700">Contactos registrados</h3>
        </header>
        @if($contactos->isEmpty())
            <div class="p-5 text-sm text-gray-500">Esta persona aún no tiene contactos.</div>
        @else
            <ul class="divide-y divide-gray-200">
                @foreach($contactos as $c)
                    <li class="px-5 py-3 flex items-center gap-3">
                        <span class="inline-block rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-700 capitalize">{{ $c->tipo }}</span>
                        <span class="text-sm text-gray-900">{{ $c->valor }}</span>
                        @if($c->etiqueta)
                            <span class="text-xs text-gray-500">· {{ $c->etiqueta }}</span>
                        @endif
                        @if($c->es_principal)
                            <span class="text-[10px] uppercase text-emerald-700 font-semibold">principal</span>
                        @endif
                        <div class="ms-auto flex items-center gap-2 text-xs">
                            @can('contactos.editar', app('tenancy.proyecto_activo')->id)
                                <button type="button" wire:click="abrirEditar({{ $c->id }})" class="text-blue-600 hover:underline">Editar</button>
                            @endcan
                            @can('contactos.eliminar', app('tenancy.proyecto_activo')->id)
                                <button type="button" wire:click="eliminar({{ $c->id }})"
                                        wire:confirm="¿Eliminar este contacto?"
                                        class="text-red-600 hover:underline">Eliminar</button>
                            @endcan
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="bg-white shadow rounded-lg p-5">
        <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-700 mb-3">
            {{ $editandoId === null ? 'Agregar contacto' : 'Editar contacto' }}
        </h3>

        <form wire:submit.prevent="{{ $editandoId === null ? 'agregar' : 'guardarEdicion' }}" class="space-y-3">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-medium uppercase tracking-wider text-gray-600 mb-1">Tipo</label>
                    <select wire:model.live="tipo" class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="telefono">Teléfono</option>
                        <option value="correo">Correo</option>
                        <option value="direccion">Dirección</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium uppercase tracking-wider text-gray-600 mb-1">
                        Valor <span class="text-red-600">*</span>
                    </label>
                    <input type="text"
                           wire:model="valor"
                           class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500"
                           placeholder="@switch($tipo)
                               @case('telefono')+593 98 123 4567@break
                               @case('correo')persona@correo.com@break
                               @default Calle, número, referencia, ciudad
                           @endswitch">
                    @error('valor') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium uppercase tracking-wider text-gray-600 mb-1">Etiqueta</label>
                    <input type="text"
                           wire:model="etiqueta"
                           class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500"
                           placeholder="Casa, trabajo, móvil…">
                </div>
                <div class="flex items-end">
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" wire:model="esPrincipal" class="text-blue-600 focus:ring-blue-500">
                        <span class="text-sm text-gray-800">Marcar como principal</span>
                    </label>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                @if($editandoId !== null)
                    <button type="button" wire:click="cancelarEdicion"
                            class="inline-flex items-center px-4 py-2 bg-white text-gray-700 border border-gray-300 text-sm font-medium rounded-md hover:bg-gray-50">
                        Cancelar
                    </button>
                @endif
                <button type="submit"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 disabled:opacity-50">
                    <span wire:loading.remove>{{ $editandoId === null ? 'Agregar contacto' : 'Guardar cambios' }}</span>
                    <span wire:loading>Guardando…</span>
                </button>
            </div>
        </form>
    </section>
</div>
