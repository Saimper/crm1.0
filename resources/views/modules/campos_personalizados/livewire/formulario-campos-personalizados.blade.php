<div>
    <div style="margin-top:18px;border-top:1px solid var(--border);padding-top:14px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
            <h4 class="text-xs font-semibold uppercase tracking-wider" style="color:var(--text-secondary);letter-spacing:0.06em;">
                Campos personalizados
            </h4>
            @if(session('campos-ok'))
                <div class="text-xs text-success-700 bg-success-50 border border-success-200 rounded px-2 py-0.5"
                     x-data="{show:true}" x-show="show" x-init="setTimeout(()=>show=false, 3000)">
                    {{ session('campos-ok') }}
                </div>
            @endif
        </div>

        @error('general')
            <div class="text-xs text-danger-700 bg-danger-50 border border-danger-200 rounded px-2 py-1" style="margin-bottom:8px;">{{ $message }}</div>
        @enderror

        @if($campos->isEmpty())
            <div class="text-xs" style="color:var(--text-tertiary);">
                Sin campos personalizados definidos para este ámbito.
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @foreach($campos as $campo)
                    @php($soloLectura = $bloqueado || ! empty($camposSoloLectura[$campo->codigo]))
                    <div>
                        <label class="block text-xs font-medium" style="color:var(--text-secondary);">
                            {{ $campo->etiqueta }}
                            @if($campo->obligatorio)<span class="text-danger-600">*</span>@endif
                        </label>

                        @switch($campo->tipo)
                            @case('texto_corto')
                                <input type="text" wire:model="valores.{{ $campo->codigo }}"
                                       @disabled($soloLectura)
                                       class="mt-1 block w-full text-sm rounded border-ink-300 focus:border-brand-500 focus:ring-brand-500"/>
                                @break
                            @case('texto_largo')
                                <textarea wire:model="valores.{{ $campo->codigo }}" rows="2"
                                          @disabled($soloLectura)
                                          class="mt-1 block w-full text-sm rounded border-ink-300 focus:border-brand-500 focus:ring-brand-500"></textarea>
                                @break
                            @case('numero_entero')
                                <input type="number" step="1" wire:model="valores.{{ $campo->codigo }}"
                                       @disabled($soloLectura)
                                       class="mt-1 block w-full text-sm rounded border-ink-300 focus:border-brand-500 focus:ring-brand-500"/>
                                @break
                            @case('numero_decimal')
                            @case('moneda')
                                <input type="text" wire:model="valores.{{ $campo->codigo }}" placeholder="0.00"
                                       @disabled($soloLectura)
                                       class="mt-1 block w-full text-sm rounded border-ink-300 focus:border-brand-500 focus:ring-brand-500"/>
                                @break
                            @case('fecha')
                                <input type="date" wire:model="valores.{{ $campo->codigo }}"
                                       @disabled($soloLectura)
                                       class="mt-1 block w-full text-sm rounded border-ink-300 focus:border-brand-500 focus:ring-brand-500"/>
                                @break
                            @case('fecha_hora')
                                <input type="datetime-local" wire:model="valores.{{ $campo->codigo }}"
                                       @disabled($soloLectura)
                                       class="mt-1 block w-full text-sm rounded border-ink-300 focus:border-brand-500 focus:ring-brand-500"/>
                                @break
                            @case('booleano')
                                <select wire:model="valores.{{ $campo->codigo }}"
                                        @disabled($soloLectura)
                                        class="mt-1 block w-full text-sm rounded border-ink-300 focus:border-brand-500 focus:ring-brand-500">
                                    <option value="">—</option>
                                    <option value="1">Sí</option>
                                    <option value="0">No</option>
                                </select>
                                @break
                            @default
                                <input type="text" wire:model="valores.{{ $campo->codigo }}"
                                       @disabled($soloLectura)
                                       class="mt-1 block w-full text-sm rounded border-ink-300 focus:border-brand-500 focus:ring-brand-500"/>
                        @endswitch
                    </div>
                @endforeach
            </div>

            @if(! $bloqueado)
                <div class="mt-3 flex items-center justify-end">
                    <button type="button" wire:click="guardar" class="btn btn-ghost btn-sm">
                        Guardar campos
                    </button>
                </div>
            @endif
        @endif
    </div>
</div>
