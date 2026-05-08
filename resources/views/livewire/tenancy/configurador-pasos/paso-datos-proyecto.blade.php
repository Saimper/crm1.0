<div>
    @if(session('paso-datos-proyecto-ok'))
        <div class="alert alert-success" style="margin-bottom:14px;">{{ session('paso-datos-proyecto-ok') }}</div>
    @endif

    <div style="display:grid;grid-template-columns:1fr;gap:14px;">
        <div style="display:grid;grid-template-columns:1fr;gap:14px;" class="md:grid-cols-2">
            <div>
                <label class="field-label">Nombre</label>
                <input type="text" wire:model="nombre"
                       class="input @error('nombre') input-error @enderror" maxlength="120"/>
                @error('nombre')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div>
                <label class="field-label">Código</label>
                <input type="text" wire:model="codigo"
                       class="input mono uppercase @error('codigo') input-error @enderror" maxlength="80"/>
                @error('codigo')<div class="field-error">{{ $message }}</div>@enderror
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr;gap:14px;" class="md:grid-cols-2">
            <div>
                <label class="field-label">Tipo de operación</label>
                <select wire:model="tipoOperacion" class="select" disabled
                        title="El tipo no se puede cambiar después de creado.">
                    @foreach($tiposOperacion as $tipo)
                        <option value="{{ $tipo->value }}">{{ ucfirst($tipo->value) }}</option>
                    @endforeach
                </select>
                <div class="field-help" style="font-size:11px;color:var(--text-tertiary);margin-top:4px;">
                    El tipo no se puede cambiar después de creado.
                </div>
            </div>
            <div>
                <label class="field-label">Estado</label>
                <label style="display:flex;align-items:center;gap:8px;padding-top:8px;">
                    <input type="checkbox" wire:model="activo"/>
                    <span style="font-size:13px;color:var(--text-secondary);">Proyecto activo</span>
                </label>
            </div>
        </div>

        <div>
            <label class="field-label">Descripción (opcional)</label>
            <textarea wire:model="descripcion" rows="3" maxlength="500"
                      class="input @error('descripcion') input-error @enderror"></textarea>
            @error('descripcion')<div class="field-error">{{ $message }}</div>@enderror
        </div>
    </div>

    <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:18px;">
        <button type="button" wire:click="guardarSinAvance" class="btn btn-ghost">Guardar</button>
        <button type="button" wire:click="guardar" class="btn btn-primary">
            <span>Guardar y continuar</span>
            <x-ui.icon name="arrow-right" :size="13" />
        </button>
    </div>
</div>
