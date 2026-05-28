<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">{{ __('casos.title_edit') }}</h1>
            <div class="page-subtitle">
                {{ __('casos.subtitle_type_edit', ['tipo' => ucfirst(str_replace('_', ' ', $tipoCaso))]) }}
                · {{ __('casos.subtitle_state_via') }}
            </div>
        </div>
    </div>

    <div class="card card-pad" style="max-width:920px;">
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;">
            <div>
                <label class="field-label">{{ __('casos.field_wallet') }}</label>
                <select wire:model.live="carteraId" class="input @error('carteraId') input-error @enderror">
                    <option value="">{{ __('casos.select_wallet') }}</option>
                    @foreach($carteras as $c)
                        <option value="{{ $c->id }}">{{ $c->nombre }}</option>
                    @endforeach
                </select>
                @error('carteraId')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div>
                <label class="field-label">{{ __('casos.field_priority') }}</label>
                <input type="number" min="0" max="9" wire:model="prioridad" class="input"/>
            </div>
            <div>
                <label class="field-label">{{ __('casos.field_entry_date') }}</label>
                <input type="date" wire:model="fechaIngreso" class="input @error('fechaIngreso') input-error @enderror"/>
                @error('fechaIngreso')<div class="field-error">{{ $message }}</div>@enderror
            </div>
        </div>

        {{-- Campos personalizados ámbito caso × cartera. Definidos por ADMIN_GLOBAL desde el wizard.
             Se persisten junto al UPDATE del caso al hacer clic en "Guardar cambios". --}}
        @if($camposCaso->isNotEmpty())
            <div style="margin-top:18px;border-top:1px solid var(--border);padding-top:14px;">
                <h4 class="text-xs font-semibold uppercase tracking-wider mb-2" style="color:var(--text-secondary);letter-spacing:0.06em;">
                    {{ __('casos.custom_fields_title') }}
                </h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach($camposCaso as $campo)
                        <div>
                            <label class="field-label">
                                {{ $campo->etiqueta }}
                                @if($campo->obligatorio)<span class="text-danger-600">*</span>@endif
                            </label>

                            @switch($campo->tipo)
                                @case('texto_corto')
                                    <input type="text" wire:model="valoresCamposCaso.{{ $campo->codigo }}" class="input"/>
                                    @break
                                @case('texto_largo')
                                    <textarea wire:model="valoresCamposCaso.{{ $campo->codigo }}" rows="2" class="input"></textarea>
                                    @break
                                @case('numero_entero')
                                    <input type="number" step="1" wire:model="valoresCamposCaso.{{ $campo->codigo }}" class="input"/>
                                    @break
                                @case('numero_decimal')
                                @case('moneda')
                                    <input type="text" wire:model="valoresCamposCaso.{{ $campo->codigo }}" placeholder="0.00" class="input"/>
                                    @break
                                @case('fecha')
                                    <input type="date" wire:model="valoresCamposCaso.{{ $campo->codigo }}" class="input"/>
                                    @break
                                @case('fecha_hora')
                                    <input type="datetime-local" wire:model="valoresCamposCaso.{{ $campo->codigo }}" class="input"/>
                                    @break
                                @case('booleano')
                                    <select wire:model="valoresCamposCaso.{{ $campo->codigo }}" class="input">
                                        <option value="">—</option>
                                        <option value="1">{{ __('casos.yes') }}</option>
                                        <option value="0">{{ __('casos.no') }}</option>
                                    </select>
                                    @break
                                @default
                                    <input type="text" wire:model="valoresCamposCaso.{{ $campo->codigo }}" class="input"/>
                            @endswitch
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div style="margin-top:20px;display:flex;justify-content:flex-end;gap:8px;">
            <a href="{{ route('proyectos.trabajo', ['proyecto_id' => app('tenancy.proyecto_activo')->id, 'persona' => $personaPublicId, 'caso' => $casoPublicId]) }}"
               wire:navigate class="btn btn-ghost">{{ __('common.cancel') }}</a>
            <button type="button" wire:click="guardar" class="btn btn-primary">
                {{ __('casos.save_changes') }}
            </button>
        </div>
    </div>
</div>
