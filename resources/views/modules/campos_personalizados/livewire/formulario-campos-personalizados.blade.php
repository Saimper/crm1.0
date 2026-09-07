<div>
    <div style="margin-top:18px;border-top:1px solid var(--border);padding-top:14px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
            <h4 class="text-xs font-semibold uppercase tracking-wider" style="color:var(--text-secondary);letter-spacing:0.06em;">
                {{ __('campos_personalizados.section_title') }}
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
                {{ __('campos_personalizados.empty_scope') }}
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

                        <x-cp.control :campo="$campo" model="valores.{{ $campo->codigo }}" :disabled="$soloLectura" />
                    </div>
                @endforeach
            </div>

            @if(! $bloqueado)
                <div class="mt-3 flex items-center justify-end">
                    <button type="button" wire:click="guardar" class="btn btn-ghost btn-sm">
                        {{ __('campos_personalizados.save_fields') }}
                    </button>
                </div>
            @endif
        @endif
    </div>
</div>
