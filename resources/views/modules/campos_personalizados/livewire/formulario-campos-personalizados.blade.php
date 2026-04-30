<div class="rounded-md border border-blue-200 bg-blue-50 p-4">
    <div class="flex items-center justify-between">
        <h4 class="text-xs font-semibold uppercase tracking-wider text-blue-800">
            Campos personalizados
        </h4>
        @if(session('campos-ok'))
            <div class="text-xs text-emerald-700 bg-emerald-50 border border-emerald-200 rounded px-2 py-0.5"
                 x-data="{show:true}" x-show="show" x-init="setTimeout(()=>show=false, 3000)">
                {{ session('campos-ok') }}
            </div>
        @endif
    </div>

    @error('general')
        <div class="mt-2 text-xs text-red-700 bg-red-50 border border-red-200 rounded px-2 py-1">{{ $message }}</div>
    @enderror

    @if($campos->isEmpty())
        <div class="mt-2 text-xs text-blue-700">No hay campos personalizados definidos para este ámbito.</div>
    @else
        <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-3">
            @foreach($campos as $campo)
                <div>
                    <label class="block text-xs font-medium text-blue-900">
                        {{ $campo->etiqueta }}
                        @if($campo->obligatorio)<span class="text-red-600">*</span>@endif
                    </label>

                    @switch($campo->tipo)
                        @case('texto_corto')
                            <input type="text" wire:model="valores.{{ $campo->codigo }}"
                                   @disabled($bloqueado)
                                   class="mt-1 block w-full text-sm rounded border-blue-300 focus:border-blue-500 focus:ring-blue-500"/>
                            @break
                        @case('texto_largo')
                            <textarea wire:model="valores.{{ $campo->codigo }}" rows="2"
                                      @disabled($bloqueado)
                                      class="mt-1 block w-full text-sm rounded border-blue-300 focus:border-blue-500 focus:ring-blue-500"></textarea>
                            @break
                        @case('numero_entero')
                            <input type="number" step="1" wire:model="valores.{{ $campo->codigo }}"
                                   @disabled($bloqueado)
                                   class="mt-1 block w-full text-sm rounded border-blue-300"/>
                            @break
                        @case('numero_decimal')
                        @case('moneda')
                            <input type="text" wire:model="valores.{{ $campo->codigo }}" placeholder="0.00"
                                   @disabled($bloqueado)
                                   class="mt-1 block w-full text-sm rounded border-blue-300"/>
                            @break
                        @case('fecha')
                            <input type="date" wire:model="valores.{{ $campo->codigo }}"
                                   @disabled($bloqueado)
                                   class="mt-1 block w-full text-sm rounded border-blue-300"/>
                            @break
                        @case('fecha_hora')
                            <input type="datetime-local" wire:model="valores.{{ $campo->codigo }}"
                                   @disabled($bloqueado)
                                   class="mt-1 block w-full text-sm rounded border-blue-300"/>
                            @break
                        @case('booleano')
                            <select wire:model="valores.{{ $campo->codigo }}"
                                    @disabled($bloqueado)
                                    class="mt-1 block w-full text-sm rounded border-blue-300">
                                <option value="">—</option>
                                <option value="1">Sí</option>
                                <option value="0">No</option>
                            </select>
                            @break
                        @default
                            <input type="text" wire:model="valores.{{ $campo->codigo }}"
                                   @disabled($bloqueado)
                                   class="mt-1 block w-full text-sm rounded border-blue-300"/>
                    @endswitch
                </div>
            @endforeach
        </div>

        @if(! $bloqueado)
            <div class="mt-3 flex items-center justify-end">
                <button type="button" wire:click="guardar"
                        class="inline-flex items-center px-3 py-1.5 text-xs text-white bg-blue-600 rounded hover:bg-blue-700">
                    Guardar campos
                </button>
            </div>
        @endif
    @endif
</div>
