<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Campos Personalizados</h1>
            <div class="page-subtitle">Atributos extendidos por proyecto · 10 tipos cerrados</div>
        </div>
        <div style="display:flex;gap:8px;">
            <a href="{{ route('admin.dashboard') }}" wire:navigate class="btn btn-ghost btn-sm">← Volver al panel</a>
            <button type="button" wire:click="abrirFormCrear" class="btn btn-primary">
                <x-ui.icon name="plus" :size="14" />
                Nuevo campo
            </button>
        </div>
    </div>

    @if(session('admin-campos-ok'))
        <div class="alert alert-success" style="margin-bottom:14px;">{{ session('admin-campos-ok') }}</div>
    @endif

    @forelse($proyectos as $p)
        @php
            $camposDeProyecto = $camposPorProyecto[$p->id] ?? collect();
        @endphp
        @if($camposDeProyecto->isNotEmpty())
            <div style="margin-bottom:14px;">
                <div style="display:flex;align-items:center;gap:10px;padding:8px 0;margin-bottom:4px;">
                    <span class="label-xs" style="margin:0;">
                        <span class="font-mono">{{ $p->codigo }}</span> · {{ $p->nombre }} · {{ $p->tipo_operacion }}
                    </span>
                    <div style="flex:1;height:1px;background:var(--border);"></div>
                    <span style="font-size:11px;color:var(--text-tertiary);">{{ $camposDeProyecto->count() }} campos</span>
                </div>
                <div class="card" style="padding:0;">
                    <table class="table table-compact">
                        <thead>
                            <tr>
                                <th style="width:110px;">Ámbito</th>
                                <th style="width:180px;">Código</th>
                                <th>Etiqueta</th>
                                <th style="width:130px;">Tipo</th>
                                <th style="width:90px;">Oblig.</th>
                                <th class="num" style="width:80px;">Orden</th>
                                <th style="width:110px;">Estado</th>
                                <th style="width:80px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($camposDeProyecto as $c)
                                <tr wire:key="campo-{{ $c->id }}" style="cursor:pointer;" wire:click="abrirFormEditar({{ $c->id }})">
                                    <td>
                                        <span class="badge badge-neutral">{{ $c->ambito }}</span>
                                        <div style="font-size:11px;color:var(--text-tertiary);margin-top:2px;">
                                            @if($c->ambito === 'caso')
                                                {{ $c->cartera_nombre ?? '#'.$c->ambito_id }}
                                            @elseif($c->ambito === 'gestion')
                                                {{ $c->tipo_gestion_nombre ?? '#'.$c->ambito_id }}
                                            @else
                                                #{{ $c->ambito_id }}
                                            @endif
                                        </div>
                                    </td>
                                    <td><span class="font-mono" style="font-size:12px;">{{ $c->codigo }}</span></td>
                                    <td>{{ $c->etiqueta }}</td>
                                    <td><span style="color:var(--text-secondary);font-size:12px;">{{ $c->tipo }}</span></td>
                                    <td>
                                        @if($c->obligatorio)
                                            <x-ui.icon name="check" :size="14" style="color:var(--success-text);" />
                                        @else
                                            <span style="color:var(--text-muted);">—</span>
                                        @endif
                                    </td>
                                    <td class="num">{{ $c->orden }}</td>
                                    <td>
                                        <span style="display:inline-flex;align-items:center;gap:6px;">
                                            <span class="dot dot-{{ $c->activo ? 'success' : 'neutral' }}"></span>
                                            {{ $c->activo ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </td>
                                    <td>
                                        <div style="display:flex;gap:2px;" wire:click.stop>
                                            <button type="button" wire:click="abrirFormEditar({{ $c->id }})" class="icon-btn" title="Editar">
                                                <x-ui.icon name="edit" :size="12" />
                                            </button>
                                            @if($c->activo)
                                                <button type="button" wire:click="desactivar({{ $c->id }})"
                                                        wire:confirm="¿Desactivar este campo?"
                                                        class="icon-btn" style="color:var(--danger-text);" title="Desactivar">
                                                    <x-ui.icon name="trash" :size="12" />
                                                </button>
                                            @else
                                                <button type="button" wire:click="activar({{ $c->id }})"
                                                        class="icon-btn" style="color:var(--success-text);" title="Activar">
                                                    <x-ui.icon name="check" :size="12" />
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @empty
        <div class="card">
            <div class="empty">
                <div class="empty-icon"><x-ui.icon name="folder" :size="32" /></div>
                <div class="empty-title">Sin proyectos</div>
                <div class="empty-desc">No hay proyectos disponibles para definir campos.</div>
            </div>
        </div>
    @endforelse

    @if($proyectos->isNotEmpty() && $camposPorProyecto->isEmpty())
        <div class="card">
            <div class="empty">
                <div class="empty-icon"><x-ui.icon name="hash" :size="32" /></div>
                <div class="empty-title">Sin campos personalizados</div>
                <div class="empty-desc">Aún no hay campos definidos en ningún proyecto.</div>
            </div>
        </div>
    @endif

    @if($formVisible)
        <div class="scrim" wire:click="cerrarForm" wire:key="form-campo-scrim"></div>
        <div class="drawer" wire:key="form-campo">
            <div class="drawer-header">
                <div style="font-size:14px;font-weight:600;">
                    {{ $campoEditandoId === null ? 'Nuevo campo' : 'Editar campo — '.$form['etiqueta'] }}
                </div>
                <button type="button" wire:click="cerrarForm" class="icon-btn" aria-label="Cerrar">
                    <x-ui.icon name="x" :size="14" />
                </button>
            </div>
            <div class="drawer-body">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                    <div style="grid-column:1 / -1;">
                        <label class="field-label">Proyecto</label>
                        <select wire:model.live="form.proyecto_id"
                                class="select @error('form.proyecto_id') input-error @enderror">
                            <option value="">—</option>
                            @foreach($proyectos as $p)
                                <option value="{{ $p->id }}">{{ $p->codigo }} — {{ $p->nombre }}</option>
                            @endforeach
                        </select>
                        @error('form.proyecto_id')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="field-label">Ámbito</label>
                        <select wire:model.live="form.ambito"
                                class="select @error('form.ambito') input-error @enderror">
                            <option value="caso">Caso × Cartera</option>
                            <option value="gestion">Gestión × Tipo gestión</option>
                        </select>
                        @error('form.ambito')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="field-label">Tipo</label>
                        <select wire:model="form.tipo"
                                class="select @error('form.tipo') input-error @enderror">
                            @foreach($tiposCampo as $t)
                                <option value="{{ $t['valor'] }}">{{ $t['etiqueta'] }}</option>
                            @endforeach
                        </select>
                        @error('form.tipo')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div style="grid-column:1 / -1;">
                        <label class="field-label">{{ $form['ambito'] === 'caso' ? 'Cartera' : 'Tipo de gestión' }}</label>
                        <select wire:model="form.ambito_id"
                                class="select @error('form.ambito_id') input-error @enderror">
                            <option value="">—</option>
                            @if($form['ambito'] === 'caso')
                                @foreach($carteras as $ca)
                                    <option value="{{ $ca->id }}">{{ $ca->codigo }} — {{ $ca->nombre }}</option>
                                @endforeach
                            @else
                                @foreach($tiposGestion as $tg)
                                    <option value="{{ $tg->id }}">{{ $tg->codigo }} — {{ $tg->nombre }}</option>
                                @endforeach
                            @endif
                        </select>
                        @error('form.ambito_id')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="field-label">Código (snake_case)</label>
                        <input type="text" wire:model="form.codigo" placeholder="operador_externo"
                               class="input mono @error('form.codigo') input-error @enderror"/>
                        @error('form.codigo')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="field-label">Orden</label>
                        <input type="number" min="0" wire:model="form.orden"
                               class="input mono @error('form.orden') input-error @enderror"/>
                        @error('form.orden')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div style="grid-column:1 / -1;">
                        <label class="field-label">Etiqueta</label>
                        <input type="text" wire:model="form.etiqueta"
                               class="input @error('form.etiqueta') input-error @enderror"/>
                        @error('form.etiqueta')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="field-label">Longitud máxima (opcional)</label>
                        <input type="number" min="1" wire:model="form.longitud_max"
                               class="input @error('form.longitud_max') input-error @enderror"/>
                        @error('form.longitud_max')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div style="display:flex;align-items:flex-end;gap:14px;">
                        <label style="display:inline-flex;align-items:center;gap:6px;font-size:13px;">
                            <input type="checkbox" wire:model="form.obligatorio" class="checkbox"/>
                            <span>Obligatorio</span>
                        </label>
                        <label style="display:inline-flex;align-items:center;gap:6px;font-size:13px;">
                            <input type="checkbox" wire:model="form.activo" class="checkbox"/>
                            <span>Activo</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="drawer-footer">
                <button type="button" wire:click="cerrarForm" class="btn btn-ghost">Cancelar</button>
                <button type="button" wire:click="guardar" class="btn btn-primary">Guardar campo</button>
            </div>
        </div>
    @endif
</div>
