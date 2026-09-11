@php
    $urlFicha = route('proyectos.trabajo', ['proyecto_id' => app('tenancy.proyecto_activo')->id, 'persona' => $personaPublicId, 'caso' => $casoPublicId]);
    $gruposEdicion = $camposCaso->groupBy(function ($campo) use ($valoresCamposCaso) {
        if (!empty($campo->grupo_nombre)) {
            return $campo->grupo_nombre;
        }
        $valor = $valoresCamposCaso[$campo->codigo] ?? null;
        if ($campo->tipo === 'texto_largo' || (is_string($valor) && mb_strlen($valor) > 240)) {
            return 'Notas y seguimiento';
        }
        return $campo->obligatorio || ($valor !== null && $valor !== '') ? 'Información de la cuenta' : 'Campos adicionales';
    });
@endphp
<div class="page account-editor">
    <a href="{{ $urlFicha }}" wire:navigate class="btn-link account-editor-back"><x-ui.icon name="chevron-left" :size="16" /> Volver a la ficha</a>
    <div class="page-header">
        <h1 class="page-title">{{ __('casos.title_edit', ['entidad' => $rotuloCaso]) }}</h1>
    </div>

    <form wire:submit="guardar" class="account-editor-form">
        @if($errors->any())
            <div role="alert" class="alert alert-danger">Revisa los campos marcados antes de guardar.</div>
        @endif
        <section class="account-editor-section" aria-labelledby="editor-main-title">
            <header><h2 id="editor-main-title">Datos principales</h2></header>
            <div class="account-editor-base">
                <div>
                    <label for="edit-wallet" class="field-label">{{ __('casos.field_wallet') }}</label>
                    <select id="edit-wallet" wire:model.live="carteraId" class="input @error('carteraId') input-error @enderror">
                        <option value="">{{ __('casos.select_wallet') }}</option>
                        @foreach($carteras as $c)<option value="{{ $c->id }}">{{ $c->nombre }}</option>@endforeach
                    </select>
                    @error('carteraId')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="edit-priority" class="field-label">{{ __('casos.field_priority') }}</label>
                    <input id="edit-priority" type="number" min="0" max="9" wire:model="prioridad" class="input" />
                    @error('prioridad')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="edit-entry" class="field-label">{{ __('casos.field_entry_date') }}</label>
                    <input id="edit-entry" type="date" wire:model="fechaIngreso" class="input @error('fechaIngreso') input-error @enderror" />
                    @error('fechaIngreso')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            </div>
        </section>

        @if($camposCaso->isNotEmpty())
            <section class="account-editor-section" x-data="{ buscar: '' }" wire:key="editor-campos-{{ $carteraId }}">
                <header class="account-editor-fields-header">
                    <h2>Datos de la cuenta</h2>
                    <label class="account-editor-search">
                        <x-ui.icon name="search" :size="16" />
                        <input type="search" x-model="buscar" placeholder="Buscar un campo…" aria-label="Buscar un campo" />
                    </label>
                </header>
                @foreach($gruposEdicion as $nombreGrupo => $camposGrupo)
                    <details class="account-editor-group" @if($nombreGrupo !== 'Campos adicionales' || $errors->any()) open @endif
                             x-bind:open="buscar !== '' ? true : $el.open">
                        <summary>{{ $nombreGrupo }} <span>{{ $camposGrupo->count() }}</span></summary>
                        <div class="account-editor-fields">
                            @foreach($camposGrupo as $campo)
                                <fieldset wire:key="editar-campo-{{ $campo->id }}"
                                          x-show="@js($errors->has('valoresCamposCaso.'.$campo->codigo)) || @js(mb_strtolower($campo->etiqueta)).includes(buscar.toLocaleLowerCase().trim())"
                                          @class(['account-editor-field', 'account-editor-field-wide' => in_array($campo->tipo, ['texto_largo', 'seleccion_multiple'], true) || (is_string($valoresCamposCaso[$campo->codigo] ?? null) && mb_strlen($valoresCamposCaso[$campo->codigo]) > 120)])>
                                    <legend class="field-label">
                                        {{ $campo->etiqueta }} @if($campo->obligatorio)<span class="text-danger-600" title="Obligatorio">*</span>@endif
                                    </legend>
                                    @if($campo->tipo === 'texto_corto' && (is_string($valoresCamposCaso[$campo->codigo] ?? null) && mb_strlen($valoresCamposCaso[$campo->codigo]) > 120))
                                        <textarea wire:model="valoresCamposCaso.{{ $campo->codigo }}" rows="3" class="input"></textarea>
                                    @else
                                        <x-cp.control :campo="$campo" model="valoresCamposCaso.{{ $campo->codigo }}" clase="input" />
                                    @endif
                                    @error('valoresCamposCaso.'.$campo->codigo)<div class="field-error">{{ $message }}</div>@enderror
                                </fieldset>
                            @endforeach
                        </div>
                    </details>
                @endforeach
            </section>
        @endif
        <footer class="account-editor-actions">
            <a href="{{ $urlFicha }}" wire:navigate class="btn btn-secondary">{{ __('common.cancel') }}</a>
            <button type="submit" wire:loading.attr="disabled" wire:target="guardar" class="btn btn-primary">
                <span wire:loading.remove wire:target="guardar">{{ __('casos.save_changes') }}</span>
                <span wire:loading wire:target="guardar">{{ __('common.saving') }}</span>
            </button>
        </footer>
    </form>
</div>
