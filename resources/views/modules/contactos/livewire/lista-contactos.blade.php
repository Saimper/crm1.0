<div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">

    @if($mensajeExito)
        <div class="rounded-md bg-success-50 border border-success-200 px-4 py-2 text-sm text-success-800"
             x-data="{}" x-init="setTimeout(() => $wire.set('mensajeExito', null), 3000)">
            {{ $mensajeExito }}
        </div>
    @endif

    <section class="bg-white shadow rounded-lg p-5">
        <div class="text-xs uppercase tracking-wider text-ink-500">Persona</div>
        <h2 class="mt-0.5 text-xl font-bold text-ink-900">{{ $nombre }}</h2>
        <div class="mt-1 text-sm text-ink-600">{{ $persona->identificacion }} · {{ ucfirst($persona->tipo_persona) }}</div>
    </section>

    <section class="bg-white shadow rounded-lg overflow-hidden">
        <header class="px-5 py-3 border-b border-ink-200">
            <h3 class="text-sm font-semibold uppercase tracking-wider text-ink-700">Contactos registrados</h3>
        </header>
        @if($contactos->isEmpty())
            <div class="p-5 text-sm text-ink-500">Esta persona aún no tiene contactos.</div>
        @else
            <ul class="divide-y divide-ink-200">
                @foreach($contactos as $c)
                    <li class="px-5 py-3 flex items-center gap-3">
                        <span class="inline-block rounded bg-ink-100 px-2 py-0.5 text-xs text-ink-700 capitalize">{{ $c->tipo }}</span>
                        <span class="text-sm text-ink-900">{{ $c->valor }}</span>
                        @if($c->etiqueta)
                            <span class="text-xs text-ink-500">· {{ $c->etiqueta }}</span>
                        @endif
                        @if($c->es_principal)
                            <span class="text-[10px] uppercase text-success-700 font-semibold">principal</span>
                        @endif
                        <div class="ms-auto flex items-center gap-2 text-xs">
                            @can('contactos.editar', app('tenancy.proyecto_activo')->id)
                                <button type="button" wire:click="abrirEditar({{ $c->id }})" class="text-brand-600 hover:underline">Editar</button>
                            @endcan
                            @can('contactos.eliminar', app('tenancy.proyecto_activo')->id)
                                <button type="button" wire:click="eliminar({{ $c->id }})"
                                        wire:confirm="¿Eliminar este contacto?"
                                        class="text-danger-600 hover:underline">Eliminar</button>
                            @endcan
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="bg-white shadow rounded-lg p-5">
        <h3 class="text-sm font-semibold uppercase tracking-wider text-ink-700 mb-3">
            {{ $editandoId === null ? 'Agregar contacto' : 'Editar contacto' }}
        </h3>

        <form wire:submit.prevent="{{ $editandoId === null ? 'agregar' : 'guardarEdicion' }}" class="space-y-3">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-medium uppercase tracking-wider text-ink-600 mb-1">Tipo</label>
                    <select wire:model.live="tipo" class="w-full rounded-md border-ink-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="telefono">Teléfono</option>
                        <option value="correo">Correo</option>
                        <option value="direccion">Dirección</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium uppercase tracking-wider text-ink-600 mb-1">
                        Valor <span class="text-danger-600">*</span>
                    </label>
                    <input type="text"
                           wire:model="valor"
                           class="w-full rounded-md border-ink-300 text-sm focus:border-brand-500 focus:ring-brand-500"
                           placeholder="@switch($tipo)
                               @case('telefono')+593 98 123 4567@break
                               @case('correo')persona@correo.com@break
                               @default Calle, número, referencia, ciudad
                           @endswitch">
                    @error('valor') <p class="mt-1 text-xs text-danger-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium uppercase tracking-wider text-ink-600 mb-1">Etiqueta</label>
                    <input type="text"
                           wire:model="etiqueta"
                           class="w-full rounded-md border-ink-300 text-sm focus:border-brand-500 focus:ring-brand-500"
                           placeholder="Casa, trabajo, móvil…">
                </div>
                <div class="flex items-end">
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" wire:model="esPrincipal" class="text-brand-600 focus:ring-brand-500">
                        <span class="text-sm text-ink-800">Marcar como principal</span>
                    </label>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                @if($editandoId !== null)
                    <button type="button" wire:click="cancelarEdicion"
                            class="inline-flex items-center px-4 py-2 bg-white text-ink-700 border border-ink-300 text-sm font-medium rounded-md hover:bg-ink-50">
                        Cancelar
                    </button>
                @endif
                <button type="submit"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center px-4 py-2 bg-brand-600 text-white text-sm font-medium rounded-md hover:bg-brand-700 disabled:opacity-50">
                    <span wire:loading.remove>{{ $editandoId === null ? 'Agregar contacto' : 'Guardar cambios' }}</span>
                    <span wire:loading>Guardando…</span>
                </button>
            </div>
        </form>
    </section>
</div>
