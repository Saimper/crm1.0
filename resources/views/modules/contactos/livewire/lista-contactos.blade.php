<div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">

    @if($mensajeExito)
        <div class="rounded-md bg-success-50 border border-success-200 px-4 py-2 text-sm text-success-800"
             x-data="{}" x-init="setTimeout(() => $wire.set('mensajeExito', null), 3000)">
            {{ $mensajeExito }}
        </div>
    @endif

    <section class="bg-white shadow rounded-lg p-5">
        <div class="text-xs uppercase tracking-wider text-ink-500">{{ __('contactos.section_person') }}</div>
        <h2 class="mt-0.5 text-xl font-bold text-ink-900">{{ $nombre }}</h2>
        <div class="mt-1 text-sm text-ink-600">{{ $persona->identificacion }} · {{ ucfirst($persona->tipo_persona) }}</div>
    </section>

    <section class="bg-white shadow rounded-lg overflow-hidden">
        <header class="px-5 py-3 border-b border-ink-200">
            <h3 class="text-sm font-semibold uppercase tracking-wider text-ink-700">{{ __('contactos.section_registered') }}</h3>
        </header>
        @if($contactos->isEmpty())
            <div class="p-5 text-sm text-ink-500">{{ __('contactos.empty_no_contacts') }}</div>
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
                            <span class="text-[10px] uppercase text-success-700 font-semibold">{{ __('contactos.badge_principal') }}</span>
                        @endif
                        <div class="ms-auto flex items-center gap-2 text-xs">
                            @can('contactos.editar', app('tenancy.proyecto_activo')->id)
                                <button type="button" wire:click="abrirEditar({{ $c->id }})" class="text-brand-600 hover:underline">{{ __('common.edit') }}</button>
                            @endcan
                            @can('contactos.eliminar', app('tenancy.proyecto_activo')->id)
                                <button type="button" wire:click="eliminar({{ $c->id }})"
                                        wire:confirm="{{ __('contactos.confirm_delete') }}"
                                        class="text-danger-600 hover:underline">{{ __('common.delete') }}</button>
                            @endcan
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="bg-white shadow rounded-lg p-5">
        <h3 class="text-sm font-semibold uppercase tracking-wider text-ink-700 mb-3">
            {{ $editandoId === null ? __('contactos.add_contact') : __('contactos.edit_contact') }}
        </h3>

        <form wire:submit.prevent="{{ $editandoId === null ? 'agregar' : 'guardarEdicion' }}" class="space-y-3">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-medium uppercase tracking-wider text-ink-600 mb-1">{{ __('contactos.field_type') }}</label>
                    <select wire:model.live="tipo" class="w-full rounded-md border-ink-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="telefono">{{ __('contactos.type_phone') }}</option>
                        <option value="correo">{{ __('contactos.type_email') }}</option>
                        <option value="direccion">{{ __('contactos.type_address') }}</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium uppercase tracking-wider text-ink-600 mb-1">
                        {{ __('contactos.field_value_req') }}
                    </label>
                    <input type="text"
                           wire:model="valor"
                           class="w-full rounded-md border-ink-300 text-sm focus:border-brand-500 focus:ring-brand-500"
                           placeholder="@switch($tipo)
                               @case('telefono'){{ __('contactos.ph_phone') }}@break
                               @case('correo'){{ __('contactos.ph_email') }}@break
                               @default{{ __('contactos.ph_address') }}
                           @endswitch">
                    @error('valor') <p class="mt-1 text-xs text-danger-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium uppercase tracking-wider text-ink-600 mb-1">{{ __('contactos.field_label') }}</label>
                    <input type="text"
                           wire:model="etiqueta"
                           class="w-full rounded-md border-ink-300 text-sm focus:border-brand-500 focus:ring-brand-500"
                           placeholder="{{ __('contactos.ph_label') }}">
                </div>
                <div class="flex items-end">
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" wire:model="esPrincipal" class="text-brand-600 focus:ring-brand-500">
                        <span class="text-sm text-ink-800">{{ __('contactos.field_principal') }}</span>
                    </label>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                @if($editandoId !== null)
                    <button type="button" wire:click="cancelarEdicion"
                            class="inline-flex items-center px-4 py-2 bg-white text-ink-700 border border-ink-300 text-sm font-medium rounded-md hover:bg-ink-50">
                        {{ __('contactos.cancel_edit') }}
                    </button>
                @endif
                <button type="submit"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center px-4 py-2 bg-brand-600 text-white text-sm font-medium rounded-md hover:bg-brand-700 disabled:opacity-50">
                    <span wire:loading.remove>{{ $editandoId === null ? __('contactos.add_contact') : __('contactos.save_changes') }}</span>
                    <span wire:loading>{{ __('contactos.saving') }}</span>
                </button>
            </div>
        </form>
    </section>
</div>
