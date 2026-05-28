<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">{{ __('personas.title_edit') }}</h1>
            <div class="page-subtitle">
                {{ __('personas.subtitle_type', ['tipo' => ucfirst($tipoPersona)]) }}
            </div>
        </div>
    </div>

    <div class="card card-pad" style="max-width:760px;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
            <div>
                <label class="field-label">{{ __('personas.field_id_type_label') }}</label>
                <select wire:model="tipoIdentificacionId" class="input @error('tipoIdentificacionId') input-error @enderror">
                    <option value="">{{ __('personas.select_option') }}</option>
                    @foreach($tiposIdentificacion as $ti)
                        <option value="{{ $ti->id }}">{{ $ti->codigo }} — {{ $ti->nombre }}</option>
                    @endforeach
                </select>
                @error('tipoIdentificacionId')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div>
                <label class="field-label">{{ __('personas.field_id_label') }}</label>
                <input type="text" wire:model="identificacion"
                       class="input mono uppercase @error('identificacion') input-error @enderror"/>
                @error('identificacion')<div class="field-error">{{ $message }}</div>@enderror
            </div>

            @if($tipoPersona === 'fisica')
                <div>
                    <label class="field-label">{{ __('personas.field_names_label') }}</label>
                    <input type="text" wire:model="nombres" class="input @error('nombres') input-error @enderror"/>
                    @error('nombres')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="field-label">{{ __('personas.field_surnames_optional') }}</label>
                    <input type="text" wire:model="apellidos" class="input"/>
                </div>
            @else
                <div style="grid-column:1 / -1;">
                    <label class="field-label">{{ __('personas.field_company_readonly') }}</label>
                    <input type="text" value="{{ $razonSocial }}" class="input" disabled/>
                </div>
            @endif
        </div>

        <div style="margin-top:20px;display:flex;justify-content:flex-end;gap:8px;">
            <a href="{{ route('proyectos.trabajo', ['proyecto_id' => app('tenancy.proyecto_activo')->id, 'persona' => $personaPublicId]) }}"
               wire:navigate class="btn btn-ghost">{{ __('common.cancel') }}</a>
            <button type="button" wire:click="guardar" class="btn btn-primary">
                {{ __('personas.save_changes') }}
            </button>
        </div>
    </div>
</div>
