<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Nuevo caso</h1>
            <div class="page-subtitle">
                Tipo de proyecto: <strong>{{ ucfirst(str_replace('_', ' ', $tipoOperacion)) }}</strong>
                @if($persona)
                    · Persona:
                    <strong>
                        @if($persona->tipo_persona === 'juridica')
                            {{ $persona->razon_social }}
                        @else
                            {{ trim(($persona->nombres ?? '').' '.($persona->apellidos ?? '')) }}
                        @endif
                    </strong>
                    · <span class="font-mono">{{ $persona->identificacion }}</span>
                @endif
            </div>
        </div>
    </div>

    @if($persona === null)
        <div class="card card-pad">
            <div class="alert alert-warning">
                Selecciona una persona desde el listado para crear un caso. La pantalla
                espera <code>?persona={ulid}</code>.
            </div>
        </div>
    @else
        @error('general')<div class="alert alert-danger" style="margin-bottom:14px;">{{ $message }}</div>@enderror

        <div class="card card-pad" style="max-width:920px;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div>
                    <label class="field-label">Cartera</label>
                    <select wire:model.live="carteraId" class="input @error('carteraId') input-error @enderror">
                        <option value="">— Selecciona —</option>
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
                    <label class="field-label">Prioridad (0–9)</label>
                    <input type="number" min="0" max="9" wire:model="prioridad" class="input"/>
                </div>
            </div>

            @if($carteraId !== '')
                <hr style="margin:20px 0;border:0;border-top:1px solid var(--border);">
                <h3 style="font-size:13px;font-weight:600;margin-bottom:10px;">
                    Información adicional del caso
                    @if($camposPersonalizados->isEmpty())
                        <span style="font-weight:400;color:var(--text-tertiary);font-size:11px;">
                            (sin campos definidos por el administrador para esta cartera)
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
                                            <span style="font-size:12px;">Sí</span>
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
                   wire:navigate class="btn btn-ghost">Cancelar</a>
                <button type="button" wire:click="guardar" class="btn btn-primary">
                    Crear caso
                </button>
            </div>
        </div>
    @endif
</div>
