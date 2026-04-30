<div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
    <div class="bg-white shadow rounded-lg overflow-hidden">
        @error('general')
            <div class="rounded-md bg-red-50 border-b border-red-200 px-6 py-3 text-sm text-red-800">{{ $message }}</div>
        @enderror

        <form wire:submit.prevent="guardar" class="p-6 space-y-5">

            <div>
                <label class="block text-xs font-medium uppercase tracking-wider text-gray-600 mb-2">Tipo de persona</label>
                <div class="flex items-center gap-3">
                    <label class="inline-flex items-center gap-2">
                        <input type="radio" wire:model.live="tipoPersona" value="fisica" class="text-blue-600 focus:ring-blue-500">
                        <span class="text-sm text-gray-800">Persona física</span>
                    </label>
                    <label class="inline-flex items-center gap-2">
                        <input type="radio" wire:model.live="tipoPersona" value="juridica" class="text-blue-600 focus:ring-blue-500">
                        <span class="text-sm text-gray-800">Persona jurídica</span>
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="sm:col-span-1">
                    <label class="block text-xs font-medium uppercase tracking-wider text-gray-600 mb-1">Tipo ID <span class="text-red-600">*</span></label>
                    <select wire:model="tipoIdentificacionId"
                            class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">— Selecciona —</option>
                        @foreach($tiposIdentificacion as $t)
                            <option value="{{ $t->id }}">{{ $t->codigo }} · {{ $t->nombre }}</option>
                        @endforeach
                    </select>
                    @error('tipoIdentificacionId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium uppercase tracking-wider text-gray-600 mb-1">Identificación <span class="text-red-600">*</span></label>
                    <input type="text"
                           wire:model="identificacion"
                           class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500"
                           placeholder="Ej: 0102030405 o 1792345678001">
                    @error('identificacion') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            @if($tipoPersona === 'fisica')
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium uppercase tracking-wider text-gray-600 mb-1">Nombres <span class="text-red-600">*</span></label>
                        <input type="text" wire:model="nombres" class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                        @error('nombres') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium uppercase tracking-wider text-gray-600 mb-1">Apellidos</label>
                        <input type="text" wire:model="apellidos" class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                        @error('apellidos') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium uppercase tracking-wider text-gray-600 mb-1">Fecha de nacimiento</label>
                    <input type="date" wire:model="fechaNacimiento" max="{{ now()->format('Y-m-d') }}" class="w-full sm:w-60 rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                    @error('fechaNacimiento') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            @else
                <div>
                    <label class="block text-xs font-medium uppercase tracking-wider text-gray-600 mb-1">Razón social <span class="text-red-600">*</span></label>
                    <input type="text" wire:model="razonSocial" class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                    @error('razonSocial') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            @endif

            <div class="flex items-center justify-between pt-3 border-t border-gray-200">
                @php $proyectoActivo = app('tenancy.proyecto_activo'); @endphp
                <a href="{{ route('proyectos.dashboard', ['proyecto_id' => $proyectoActivo->id]) }}" wire:navigate class="text-sm text-gray-600 hover:text-gray-900">Cancelar</a>
                <button type="submit"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 disabled:opacity-50">
                    <span wire:loading.remove>Crear persona</span>
                    <span wire:loading>Guardando…</span>
                </button>
            </div>
        </form>
    </div>
</div>
