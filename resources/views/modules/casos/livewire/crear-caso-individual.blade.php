<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">{{ __('casos.title_create') }}</h1>
            <div class="page-subtitle">
                {{ __('casos.subtitle_type', ['tipo' => ucfirst(str_replace('_', ' ', $tipoOperacion))]) }}
                @if($persona)
                    · {{ __('casos.subtitle_person', [
                        'nombre' => $persona->tipo_persona === 'juridica'
                            ? $persona->razon_social
                            : trim(($persona->nombres ?? '').' '.($persona->apellidos ?? ''))
                    ]) }}
                    · <span class="font-mono">{{ $persona->identificacion }}</span>
                @endif
            </div>
        </div>
    </div>

    @if($persona === null)
        <div class="card card-pad">
            <div class="alert alert-warning">
                {!! __('casos.no_person_alert') !!}
            </div>
        </div>
    @else
        @error('general')<div class="alert alert-danger" style="margin-bottom:14px;">{{ $message }}</div>@enderror

        <div class="card card-pad" style="max-width:920px;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
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
                    <label class="field-label">{{ $etiquetaIdUnico }}</label>
                    <input type="text" wire:model="idUnico" class="input mono uppercase @error('idUnico') input-error @enderror"/>
                    @error('idUnico')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="field-label">{{ __('casos.field_priority') }}</label>
                    <input type="number" min="0" max="9" wire:model="prioridad" class="input"/>
                </div>
            </div>

            @if($carteraId !== '')
                <hr style="margin:20px 0;border:0;border-top:1px solid var(--border);">
                <h3 style="font-size:13px;font-weight:600;margin-bottom:10px;">
                    {{ __('casos.additional_info') }}
                    @if($camposPersonalizados->isEmpty())
                        <span style="font-weight:400;color:var(--text-tertiary);font-size:11px;">
                            {{ __('casos.no_custom_fields') }}
                        </span>
                    @endif
                </h3>

                @if($camposPersonalizados->isNotEmpty())
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                        @foreach($camposPersonalizados as $campo)
                            @php
                                $key = (string) $campo->codigo;
                                $tipo = (string) $campo->tipo;
                                $etiqueta = (string) $campo->etiqueta;
                                $req = (bool) $campo->obligatorio;
                            @endphp
                            <div @if(in_array($tipo, ['texto_largo', 'seleccion_multiple'], true)) style="grid-column:1 / -1;" @endif>
                                <label class="field-label">
                                    {{ $etiqueta }}
                                    @if($req)<span style="color:var(--danger);">*</span>@endif
                                </label>
                                @switch($tipo)
                                    @case('texto_corto')
                                        <input type="text" wire:model="valoresCp.{{ $key }}" class="input"/>
                                        @break
                                    @case('texto_largo')
                                        <textarea rows="3" wire:model="valoresCp.{{ $key }}" class="input"></textarea>
                                        @break
                                    @case('numero_entero')
                                        <input type="number" step="1" wire:model="valoresCp.{{ $key }}" class="input mono"/>
                                        @break
                                    @case('numero_decimal')
                                    @case('moneda')
                                        <input type="number" step="0.01" wire:model="valoresCp.{{ $key }}" class="input mono"/>
                                        @break
                                    @case('fecha')
                                        <input type="date" wire:model="valoresCp.{{ $key }}" class="input"/>
                                        @break
                                    @case('fecha_hora')
                                        <input type="datetime-local" wire:model="valoresCp.{{ $key }}" class="input"/>
                                        @break
                                    @case('booleano')
                                        <label style="display:flex;align-items:center;gap:6px;">
                                            <input type="checkbox" wire:model="valoresCp.{{ $key }}"/>
                                            <span style="font-size:12px;">{{ __('casos.yes') }}</span>
                                        </label>
                                        @break
                                    @default
                                        <input type="text" wire:model="valoresCp.{{ $key }}" class="input"/>
                                @endswitch
                                @if($campo->descripcion)
                                    <div style="font-size:11px;color:var(--text-tertiary);margin-top:4px;">{{ $campo->descripcion }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            @endif

            <div style="margin-top:20px;display:flex;justify-content:flex-end;gap:8px;">
                <a href="{{ route('proyectos.trabajo', ['proyecto_id' => app('tenancy.proyecto_activo')->id, 'persona' => $personaPublicId]) }}"
                   wire:navigate class="btn btn-ghost">{{ __('common.cancel') }}</a>
                <button type="button" wire:click="guardar" class="btn btn-primary">
                    {{ __('casos.create_case') }}
                </button>
            </div>
        </div>
    @endif
</div>
