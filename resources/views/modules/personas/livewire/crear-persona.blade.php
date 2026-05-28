<div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
    <div class="bg-white shadow rounded-lg overflow-hidden">
        @error('general')
            <div class="rounded-md bg-danger-50 border-b border-danger-200 px-6 py-3 text-sm text-danger-700">{{ $message }}</div>
        @enderror

        <form wire:submit.prevent="guardar" class="p-6 space-y-5">

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="sm:col-span-1">
                    <label class="block text-xs font-medium uppercase tracking-wider text-ink-600 mb-1">{{ __('personas.field_id_type_req') }}</label>
                    <select wire:model="tipoIdentificacionId"
                            class="w-full rounded-md border-ink-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">{{ __('personas.select_option') }}</option>
                        @foreach($tiposIdentificacion as $t)
                            <option value="{{ $t->id }}">{{ $t->codigo }} · {{ $t->nombre }}</option>
                        @endforeach
                    </select>
                    @error('tipoIdentificacionId') <p class="mt-1 text-xs text-danger-600">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium uppercase tracking-wider text-ink-600 mb-1">{{ __('personas.field_id_req') }}</label>
                    <input type="text"
                           wire:model="identificacion"
                           class="w-full rounded-md border-ink-300 text-sm focus:border-brand-500 focus:ring-brand-500"
                           placeholder="{{ __('personas.id_placeholder') }}">
                    @error('identificacion') <p class="mt-1 text-xs text-danger-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium uppercase tracking-wider text-ink-600 mb-1">{{ __('personas.field_names_req') }}</label>
                    <input type="text" wire:model="nombres" class="w-full rounded-md border-ink-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                    @error('nombres') <p class="mt-1 text-xs text-danger-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium uppercase tracking-wider text-ink-600 mb-1">{{ __('personas.field_surnames') }}</label>
                    <input type="text" wire:model="apellidos" class="w-full rounded-md border-ink-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                    @error('apellidos') <p class="mt-1 text-xs text-danger-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex items-center justify-between pt-3 border-t border-ink-200">
                @php $proyectoActivo = app('tenancy.proyecto_activo'); @endphp
                <a href="{{ route('proyectos.dashboard', ['proyecto_id' => $proyectoActivo->id]) }}" wire:navigate class="text-sm text-ink-600 hover:text-ink-900">{{ __('common.cancel') }}</a>
                <button type="submit"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center px-4 py-2 bg-brand-600 text-white text-sm font-medium rounded-md hover:bg-brand-700 disabled:opacity-50">
                    <span wire:loading.remove>{{ __('personas.create_person') }}</span>
                    <span wire:loading>{{ __('personas.saving') }}</span>
                </button>
            </div>
        </form>
    </div>
</div>
