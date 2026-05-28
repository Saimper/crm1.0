<div>
    @if(session('paso-campos-personalizados-ok'))<div class="alert alert-success" style="margin-bottom:14px;">{{ session('paso-campos-personalizados-ok') }}</div>@endif
    @if(session('paso-campos-personalizados-error'))<div class="alert alert-warning" style="margin-bottom:14px;">{{ session('paso-campos-personalizados-error') }}</div>@endif

    <div class="alert alert-info" style="margin-bottom:14px;font-size:12px;">
        {{ __('configurador.campos.info_opcional') }}
    </div>

    <div class="card" style="padding:0;">
        <div style="padding:12px 16px;border-bottom:1px solid var(--border);display:flex;gap:10px;align-items:center;">
            <div style="position:relative;width:280px;">
                <span style="position:absolute;left:9px;top:11px;color:var(--text-muted);pointer-events:none;"><x-ui.icon name="search" :size="13"/></span>
                <input type="text" wire:model.live.debounce.300ms="busqueda" class="input" placeholder="{{ __('common.search') }}…" style="padding-left:28px;"/>
            </div>
            <span style="flex:1;"></span>
            <span style="font-size:12px;color:var(--text-tertiary);">{{ __('configurador.campos.n_campos', ['n' => $campos->count()]) }}</span>
            <button type="button" wire:click="abrirFormCrear" class="btn btn-primary"><x-ui.icon name="plus" :size="14"/><span>{{ __('configurador.campos.nuevo') }}</span></button>
        </div>

        @if($campos->isEmpty())
            <div class="empty">
                <div class="empty-icon"><x-ui.icon name="hash" :size="32"/></div>
                <div class="empty-title">{{ __('configurador.campos.sin_titulo') }}</div>
                <div class="empty-desc">{{ __('configurador.campos.sin_desc') }}</div>
            </div>
        @else
            <table class="table table-compact table-clickable">
                <thead>
                    <tr>
                        <th style="width:90px;">{{ __('configurador.campos.col_ambito') }}</th>
                        <th style="width:160px;">{{ __('configurador.campos.col_sub_ambito') }}</th>
                        <th style="width:160px;">{{ __('configurador.campo_codigo') }}</th>
                        <th>{{ __('configurador.campos.col_etiqueta') }}</th>
                        <th style="width:120px;">{{ __('configurador.campos.col_tipo') }}</th>
                        <th style="width:80px;">{{ __('configurador.campos.col_obligatorio') }}</th>
                        <th class="num" style="width:70px;">{{ __('configurador.campo_orden') }}</th>
                        <th style="width:110px;">{{ __('configurador.campo_estado') }}</th>
                        <th style="width:60px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($campos as $c)
                        <tr wire:key="paso-cp-{{ $c->id }}" wire:click="abrirFormEditar({{ $c->id }})">
                            <td><span style="font-size:11px;text-transform:uppercase;color:var(--text-tertiary);">{{ $c->ambito }}</span></td>
                            <td><span style="font-size:12px;color:var(--text-secondary);">{{ $c->cartera_nombre ?? $c->tipo_gestion_nombre ?? '—' }}</span></td>
                            <td><span class="font-mono" style="font-size:12px;">{{ $c->codigo }}</span></td>
                            <td><span style="font-weight:500;">{{ $c->etiqueta }}</span></td>
                            <td><span style="font-size:11px;color:var(--text-secondary);">{{ str_replace('_', ' ', $c->tipo) }}</span></td>
                            <td>
                                @if($c->obligatorio)
                                    <span class="badge badge-warning">{{ __('configurador.resultados.si') }}</span>
                                @else
                                    <span style="font-size:12px;color:var(--text-muted);">—</span>
                                @endif
                            </td>
                            <td class="num">{{ $c->orden }}</td>
                            <td>
                                <span style="display:inline-flex;align-items:center;gap:6px;">
                                    <span class="dot dot-{{ $c->activo ? 'success' : 'neutral' }}"></span>
                                    {{ $c->activo ? __('configurador.activo') : __('configurador.inactivo') }}
                                </span>
                            </td>
                            <td><x-ui.icon name="chevron-right" :size="14" style="color:var(--text-muted);"/></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @if($formVisible)
        <div class="scrim" wire:click="cerrarForm" wire:key="paso-cp-scrim"></div>
        <div class="drawer" wire:key="paso-cp-drawer">
            <div class="drawer-header">
                <div style="font-size:14px;font-weight:600;">{{ $editandoId === null ? __('configurador.campos.drawer_nuevo') : __('configurador.campos.drawer_editar') }}</div>
                <button type="button" wire:click="cerrarForm" class="icon-btn"><x-ui.icon name="x" :size="14"/></button>
            </div>
            <div class="drawer-body">
                <div style="display:grid;grid-template-columns:1fr;gap:14px;">
                    <div>
                        <label class="field-label">{{ __('configurador.campos.campo_ambito') }}</label>
                        <select wire:model.live="form.ambito" class="select @error('form.ambito') input-error @enderror">
                            <option value="caso">{{ __('configurador.campos.ambito_caso') }}</option>
                            <option value="gestion">{{ __('configurador.campos.ambito_gestion') }}</option>
                        </select>
                        @error('form.ambito')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="field-label">{{ $form['ambito'] === 'gestion' ? __('configurador.campos.label_tipo_gestion') : __('configurador.campos.label_cartera') }}</label>
                        <select wire:model="form.ambito_id" class="select @error('form.ambito_id') input-error @enderror">
                            <option value="">{{ __('configurador.campos.seleccionar') }}</option>
                            @if($form['ambito'] === 'gestion')
                                @foreach($tiposGestion as $t)
                                    <option value="{{ $t->id }}">{{ $t->codigo }} — {{ $t->nombre }}</option>
                                @endforeach
                            @else
                                @foreach($carteras as $c)
                                    <option value="{{ $c->id }}">{{ $c->codigo }} — {{ $c->nombre }}</option>
                                @endforeach
                            @endif
                        </select>
                        @error('form.ambito_id')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="field-label">{{ __('configurador.campo_codigo') }}</label>
                        <input type="text" wire:model="form.codigo" maxlength="80" placeholder="dias_antiguedad"
                               class="input mono @error('form.codigo') input-error @enderror"/>
                        @error('form.codigo')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="field-label">{{ __('configurador.campos.campo_etiqueta') }}</label>
                        <input type="text" wire:model="form.etiqueta" maxlength="200"
                               class="input @error('form.etiqueta') input-error @enderror"/>
                        @error('form.etiqueta')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="field-label">{{ __('configurador.campo_descripcion') }}</label>
                        <textarea wire:model="form.descripcion" rows="2" maxlength="500" class="input"></textarea>
                    </div>
                    <div>
                        <label class="field-label">{{ __('configurador.campos.campo_tipo') }}</label>
                        <select wire:model="form.tipo" class="select @error('form.tipo') input-error @enderror">
                            @foreach($tiposCampo as $t)
                                <option value="{{ $t['valor'] }}">{{ $t['etiqueta'] }}</option>
                            @endforeach
                        </select>
                        @error('form.tipo')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="field-label">{{ __('configurador.campos.longitud_max') }}</label>
                        <input type="number" min="1" wire:model="form.longitud_max" class="input"/>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                        <div>
                            <label class="field-label">{{ __('configurador.campo_orden') }}</label>
                            <input type="number" min="0" wire:model="form.orden" class="input"/>
                        </div>
                        <div>
                            <label class="field-label">{{ __('configurador.campos.obligatorio') }}</label>
                            <label style="display:flex;align-items:center;gap:8px;padding-top:8px;">
                                <input type="checkbox" wire:model="form.obligatorio"/>
                                <span style="font-size:13px;">{{ __('configurador.campos.obligatorio') }}</span>
                            </label>
                        </div>
                    </div>
                    <div>
                        <label style="display:flex;align-items:center;gap:8px;">
                            <input type="checkbox" wire:model="form.activo"/>
                            <span style="font-size:13px;">{{ __('configurador.campos.campo_activo') }}</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="drawer-footer">
                @if($editandoId !== null)
                    <button type="button" wire:click="eliminar({{ $editandoId }})" wire:confirm="{{ __('configurador.campos.confirm_eliminar') }}"
                            class="btn btn-ghost" style="color:var(--danger-text);margin-right:auto;">{{ __('common.delete') }}</button>
                @endif
                <button type="button" wire:click="cerrarForm" class="btn btn-ghost">{{ __('common.cancel') }}</button>
                <button type="button" wire:click="guardar" class="btn btn-primary">{{ __('common.save') }}</button>
            </div>
        </div>
    @endif
</div>
