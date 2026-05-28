<div>
    @if(session('paso-datos-proyecto-ok'))
        <div class="alert alert-success" style="margin-bottom:14px;">{{ session('paso-datos-proyecto-ok') }}</div>
    @endif

    <div style="display:grid;grid-template-columns:1fr;gap:14px;">
        <div style="display:grid;grid-template-columns:1fr;gap:14px;" class="md:grid-cols-2">
            <div>
                <label class="field-label">{{ __('common.name') }}</label>
                <input type="text" wire:model="nombre"
                       class="input @error('nombre') input-error @enderror" maxlength="120"/>
                @error('nombre')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div>
                <label class="field-label">{{ __('configurador.campo_codigo') }}</label>
                <input type="text" wire:model="codigo"
                       class="input mono uppercase @error('codigo') input-error @enderror" maxlength="80"/>
                @error('codigo')<div class="field-error">{{ $message }}</div>@enderror
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr;gap:14px;" class="md:grid-cols-2">
            <div>
                <label class="field-label">{{ __('configurador.datos.tipo_operacion') }}</label>
                <select wire:model="tipoOperacion" class="select" disabled
                        title="{{ __('configurador.datos.tipo_no_cambia') }}">
                    @foreach($tiposOperacion as $tipo)
                        <option value="{{ $tipo->value }}">{{ ucfirst($tipo->value) }}</option>
                    @endforeach
                </select>
                <div class="field-help" style="font-size:11px;color:var(--text-tertiary);margin-top:4px;">
                    {{ __('configurador.datos.tipo_no_cambia') }}
                </div>
            </div>
            <div>
                <label class="field-label">{{ __('configurador.datos.estado') }}</label>
                <label style="display:flex;align-items:center;gap:8px;padding-top:8px;">
                    <input type="checkbox" wire:model="activo"/>
                    <span style="font-size:13px;color:var(--text-secondary);">{{ __('configurador.datos.proyecto_activo') }}</span>
                </label>
            </div>
        </div>

        <div>
            <label class="field-label">{{ __('configurador.campo_descripcion') }}</label>
            <textarea wire:model="descripcion" rows="3" maxlength="500"
                      class="input @error('descripcion') input-error @enderror"></textarea>
            @error('descripcion')<div class="field-error">{{ $message }}</div>@enderror
        </div>
    </div>

    <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:18px;">
        <button type="button" wire:click="guardarSinAvance" class="btn btn-ghost">{{ __('common.save') }}</button>
        <button type="button" wire:click="guardar" class="btn btn-primary">
            <span>{{ __('configurador.datos.guardar_continuar') }}</span>
            <x-ui.icon name="arrow-right" :size="13" />
        </button>
    </div>
</div>
