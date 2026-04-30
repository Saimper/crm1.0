<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Editar persona</h1>
            <div class="page-subtitle">
                Tipo: <strong>{{ ucfirst($tipoPersona) }}</strong> (no editable)
            </div>
        </div>
    </div>

    <div class="card card-pad" style="max-width:760px;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
            <div>
                <label class="field-label">Tipo de identificación</label>
                <select wire:model="tipoIdentificacionId" class="input @error('tipoIdentificacionId') input-error @enderror">
                    <option value="">— Selecciona —</option>
                    @foreach($tiposIdentificacion as $ti)
                        <option value="{{ $ti->id }}">{{ $ti->codigo }} — {{ $ti->nombre }}</option>
                    @endforeach
                </select>
                @error('tipoIdentificacionId')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div>
                <label class="field-label">Identificación</label>
                <input type="text" wire:model="identificacion"
                       class="input mono uppercase @error('identificacion') input-error @enderror"/>
                @error('identificacion')<div class="field-error">{{ $message }}</div>@enderror
            </div>

            @if($tipoPersona === 'fisica')
                <div>
                    <label class="field-label">Nombres</label>
                    <input type="text" wire:model="nombres" class="input @error('nombres') input-error @enderror"/>
                    @error('nombres')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="field-label">Apellidos (opcional)</label>
                    <input type="text" wire:model="apellidos" class="input"/>
                </div>
                <div>
                    <label class="field-label">Fecha nacimiento (opcional)</label>
                    <input type="date" wire:model="fechaNacimiento" class="input"/>
                </div>
            @else
                <div style="grid-column:1 / -1;">
                    <label class="field-label">Razón social</label>
                    <input type="text" wire:model="razonSocial" class="input @error('razonSocial') input-error @enderror"/>
                    @error('razonSocial')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            @endif
        </div>

        <div style="margin-top:20px;display:flex;justify-content:flex-end;gap:8px;">
            <a href="{{ route('proyectos.trabajo', ['proyecto_id' => app('tenancy.proyecto_activo')->id, 'persona' => $personaPublicId]) }}"
               wire:navigate class="btn btn-ghost">Cancelar</a>
            <button type="button" wire:click="guardar" class="btn btn-primary">
                Guardar cambios
            </button>
        </div>
    </div>
</div>
