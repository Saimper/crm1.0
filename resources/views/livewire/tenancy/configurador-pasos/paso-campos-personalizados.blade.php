<div>
    @if(session('paso-campos-personalizados-ok'))<div class="alert alert-success" style="margin-bottom:14px;">{{ session('paso-campos-personalizados-ok') }}</div>@endif
    @if(session('paso-campos-personalizados-error'))<div class="alert alert-warning" style="margin-bottom:14px;">{{ session('paso-campos-personalizados-error') }}</div>@endif

    <div class="alert alert-info" style="margin-bottom:14px;font-size:12px;">
        <strong>Paso opcional.</strong> Los campos personalizados extienden el modelo de datos
        del proyecto sin migrar schema. Puedes completarlo después desde el panel de
        administración.
    </div>

    <div class="card" style="padding:0;">
        <div style="padding:12px 16px;border-bottom:1px solid var(--border);display:flex;gap:10px;align-items:center;">
            <div style="position:relative;width:280px;">
                <span style="position:absolute;left:9px;top:11px;color:var(--text-muted);pointer-events:none;"><x-ui.icon name="search" :size="13"/></span>
                <input type="text" wire:model.live.debounce.300ms="busqueda" class="input" placeholder="Buscar…" style="padding-left:28px;"/>
            </div>
            <span style="flex:1;"></span>
            <span style="font-size:12px;color:var(--text-tertiary);">{{ $campos->count() }} campos</span>
            <button type="button" wire:click="abrirFormCrear" class="btn btn-primary"><x-ui.icon name="plus" :size="14"/><span>Nuevo campo</span></button>
        </div>

        @if($campos->isEmpty())
            <div class="empty">
                <div class="empty-icon"><x-ui.icon name="hash" :size="32"/></div>
                <div class="empty-title">Sin campos personalizados</div>
                <div class="empty-desc">Define campos por cartera (caso) o por tipo de gestión.</div>
            </div>
        @else
            <table class="table table-compact table-clickable">
                <thead>
                    <tr>
                        <th style="width:90px;">Ámbito</th>
                        <th style="width:160px;">Sub-ámbito</th>
                        <th style="width:160px;">Código</th>
                        <th>Etiqueta</th>
                        <th style="width:120px;">Tipo</th>
                        <th style="width:80px;">Obligatorio</th>
                        <th class="num" style="width:70px;">Orden</th>
                        <th style="width:110px;">Estado</th>
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
                                    <span class="badge badge-warning">Sí</span>
                                @else
                                    <span style="font-size:12px;color:var(--text-muted);">—</span>
                                @endif
                            </td>
                            <td class="num">{{ $c->orden }}</td>
                            <td>
                                <span style="display:inline-flex;align-items:center;gap:6px;">
                                    <span class="dot dot-{{ $c->activo ? 'success' : 'neutral' }}"></span>
                                    {{ $c->activo ? 'Activo' : 'Inactivo' }}
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
                <div style="font-size:14px;font-weight:600;">{{ $editandoId === null ? 'Nuevo campo personalizado' : 'Editar campo personalizado' }}</div>
                <button type="button" wire:click="cerrarForm" class="icon-btn"><x-ui.icon name="x" :size="14"/></button>
            </div>
            <div class="drawer-body">
                <div style="display:grid;grid-template-columns:1fr;gap:14px;">
                    <div>
                        <label class="field-label">Ámbito</label>
                        <select wire:model.live="form.ambito" class="select @error('form.ambito') input-error @enderror">
                            <option value="caso">Caso (× cartera)</option>
                            <option value="gestion">Gestión (× tipo de gestión)</option>
                        </select>
                        @error('form.ambito')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="field-label">{{ $form['ambito'] === 'gestion' ? 'Tipo de gestión' : 'Cartera' }}</label>
                        <select wire:model="form.ambito_id" class="select @error('form.ambito_id') input-error @enderror">
                            <option value="">— Seleccionar —</option>
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
                        <label class="field-label">Código</label>
                        <input type="text" wire:model="form.codigo" maxlength="80" placeholder="dias_antiguedad"
                               class="input mono @error('form.codigo') input-error @enderror"/>
                        @error('form.codigo')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="field-label">Etiqueta</label>
                        <input type="text" wire:model="form.etiqueta" maxlength="200"
                               class="input @error('form.etiqueta') input-error @enderror"/>
                        @error('form.etiqueta')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="field-label">Descripción (opcional)</label>
                        <textarea wire:model="form.descripcion" rows="2" maxlength="500" class="input"></textarea>
                    </div>
                    <div>
                        <label class="field-label">Tipo</label>
                        <select wire:model="form.tipo" class="select @error('form.tipo') input-error @enderror">
                            @foreach($tiposCampo as $t)
                                <option value="{{ $t['valor'] }}">{{ $t['etiqueta'] }}</option>
                            @endforeach
                        </select>
                        @error('form.tipo')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="field-label">Longitud máxima (opcional, solo texto)</label>
                        <input type="number" min="1" wire:model="form.longitud_max" class="input"/>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                        <div>
                            <label class="field-label">Orden</label>
                            <input type="number" min="0" wire:model="form.orden" class="input"/>
                        </div>
                        <div>
                            <label class="field-label">Obligatorio</label>
                            <label style="display:flex;align-items:center;gap:8px;padding-top:8px;">
                                <input type="checkbox" wire:model="form.obligatorio"/>
                                <span style="font-size:13px;">Obligatorio</span>
                            </label>
                        </div>
                    </div>
                    <div>
                        <label style="display:flex;align-items:center;gap:8px;">
                            <input type="checkbox" wire:model="form.activo"/>
                            <span style="font-size:13px;">Campo activo</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="drawer-footer">
                @if($editandoId !== null)
                    <button type="button" wire:click="eliminar({{ $editandoId }})" wire:confirm="¿Eliminar este campo? Solo se permite si no hay valores capturados."
                            class="btn btn-ghost" style="color:var(--danger-text);margin-right:auto;">Eliminar</button>
                @endif
                <button type="button" wire:click="cerrarForm" class="btn btn-ghost">Cancelar</button>
                <button type="button" wire:click="guardar" class="btn btn-primary">Guardar</button>
            </div>
        </div>
    @endif
</div>
