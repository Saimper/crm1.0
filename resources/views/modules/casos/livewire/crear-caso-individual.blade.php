<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">{{ __('casos.title_create', ['entidad' => $rotuloCaso]) }}</h1>
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
                {!! __('casos.no_person_alert', ['entidad' => $rotuloCaso, 'un' => $rotuloArticulo]) !!}
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

            {{-- Elegir cartera va al servidor a por los campos personalizados y
                 repinta medio formulario. Acotada a ese viaje, no al de guardar. --}}
            <x-ui.cargando target="carteraId" />

            @if($carteraId !== '')
                <hr style="margin:20px 0;border:0;border-top:1px solid var(--border);">
                <h3 class="text-base font-semibold" style="margin-bottom:10px;">
                    {{ __('casos.additional_info') }}
                    @if($camposPersonalizados->isEmpty())
                        <span class="font-normal text-ink-500 text-xs">
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
                                <x-cp.control :campo="$campo" model="valoresCp.{{ $key }}" clase="input" />
                                @if($campo->descripcion)
                                    <div class="text-xs text-ink-500" style="margin-top:4px;">{{ $campo->descripcion }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            @endif

            <div class="flex gap-2" style="margin-top:20px;justify-content:flex-end;">
                <a href="{{ route('proyectos.trabajo', ['proyecto_id' => app('tenancy.proyecto_activo')->id, 'persona' => $personaPublicId]) }}"
                   wire:navigate class="btn btn-ghost">{{ __('common.cancel') }}</a>
                <button type="button" wire:click="guardar" class="btn btn-primary">
                    {{ __('casos.create_case', ['entidad' => $rotuloCaso]) }}
                </button>
            </div>
        </div>
    @endif
</div>
