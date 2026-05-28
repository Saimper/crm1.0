<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">{{ __('campos_personalizados.title') }}</h1>
            <div class="page-subtitle">{{ __('campos_personalizados.subtitle') }}</div>
        </div>
        <div style="display:flex;gap:8px;">
            <a href="{{ route('admin.dashboard') }}" wire:navigate class="btn btn-ghost btn-sm">{{ __('campos_personalizados.back_to_panel') }}</a>
            <button type="button" wire:click="abrirFormCrear" class="btn btn-primary">
                <x-ui.icon name="plus" :size="14" />
                {{ __('campos_personalizados.new_field') }}
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
                    <span style="font-size:11px;color:var(--text-tertiary);">{{ __('campos_personalizados.count_fields', ['count' => $camposDeProyecto->count()]) }}</span>
                </div>
                <div class="card" style="padding:0;">
                    <table class="table table-compact">
                        <thead>
                            <tr>
                                <th style="width:110px;">{{ __('campos_personalizados.col_scope') }}</th>
                                <th style="width:180px;">{{ __('campos_personalizados.col_code') }}</th>
                                <th>{{ __('campos_personalizados.col_label') }}</th>
                                <th style="width:130px;">{{ __('campos_personalizados.col_type') }}</th>
                                <th style="width:90px;">{{ __('campos_personalizados.col_required') }}</th>
                                <th class="num" style="width:80px;">{{ __('campos_personalizados.col_order') }}</th>
                                <th style="width:110px;">{{ __('campos_personalizados.col_status') }}</th>
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
                                            {{ $c->activo ? __('campos_personalizados.status_active') : __('campos_personalizados.status_inactive') }}
                                        </span>
                                    </td>
                                    <td>
                                        <div style="display:flex;gap:2px;" wire:click.stop>
                                            <button type="button" wire:click="abrirFormEditar({{ $c->id }})" class="icon-btn" :title="__('common.edit')">
                                                <x-ui.icon name="edit" :size="12" />
                                            </button>
                                            @if($c->activo)
                                                <button type="button" wire:click="desactivar({{ $c->id }})"
                                                        wire:confirm="{{ __('campos_personalizados.confirm_deactivate') }}"
                                                        class="icon-btn" style="color:var(--danger-text);" :title="__('campos_personalizados.title_deactivate')">
                                                    <x-ui.icon name="trash" :size="12" />
                                                </button>
                                            @else
                                                <button type="button" wire:click="activar({{ $c->id }})"
                                                        class="icon-btn" style="color:var(--success-text);" :title="__('campos_personalizados.title_activate')">
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
                <div class="empty-title">{{ __('campos_personalizados.empty_no_projects') }}</div>
                <div class="empty-desc">{{ __('campos_personalizados.empty_no_projects_desc') }}</div>
            </div>
        </div>
    @endforelse

    @if($proyectos->isNotEmpty() && $camposPorProyecto->isEmpty())
        <div class="card">
            <div class="empty">
                <div class="empty-icon"><x-ui.icon name="hash" :size="32" /></div>
                <div class="empty-title">{{ __('campos_personalizados.empty_no_fields') }}</div>
                <div class="empty-desc">{{ __('campos_personalizados.empty_no_fields_desc') }}</div>
            </div>
        </div>
    @endif

    @if($formVisible)
        <div class="scrim" wire:click="cerrarForm" wire:key="form-campo-scrim"></div>
        <div class="drawer" wire:key="form-campo">
            <div class="drawer-header">
                <div style="font-size:14px;font-weight:600;">
                    {{ $campoEditandoId === null ? __('campos_personalizados.drawer_new') : __('campos_personalizados.drawer_edit', ['label' => $form['etiqueta']]) }}
                </div>
                <button type="button" wire:click="cerrarForm" class="icon-btn" :aria-label="__('campos_personalizados.close')">
                    <x-ui.icon name="x" :size="14" />
                </button>
            </div>
            <div class="drawer-body">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                    <div style="grid-column:1 / -1;">
                        <label class="field-label">{{ __('campos_personalizados.label_project') }}</label>
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
                        <label class="field-label">{{ __('campos_personalizados.label_scope') }}</label>
                        <select wire:model.live="form.ambito"
                                class="select @error('form.ambito') input-error @enderror">
                            <option value="caso">{{ __('campos_personalizados.scope_case') }}</option>
                            <option value="gestion">{{ __('campos_personalizados.scope_gestion') }}</option>
                        </select>
                        @error('form.ambito')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="field-label">{{ __('campos_personalizados.label_type') }}</label>
                        <select wire:model="form.tipo"
                                class="select @error('form.tipo') input-error @enderror">
                            @foreach($tiposCampo as $t)
                                <option value="{{ $t['valor'] }}">{{ $t['etiqueta'] }}</option>
                            @endforeach
                        </select>
                        @error('form.tipo')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div style="grid-column:1 / -1;">
                        <label class="field-label">{{ $form['ambito'] === 'caso' ? __('campos_personalizados.label_scope_id') : __('campos_personalizados.label_scope_gestion_id') }}</label>
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
                        <label class="field-label">{{ __('campos_personalizados.label_code') }}</label>
                        <input type="text" wire:model="form.codigo" placeholder="operador_externo"
                               class="input mono @error('form.codigo') input-error @enderror"/>
                        @error('form.codigo')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="field-label">{{ __('campos_personalizados.label_order') }}</label>
                        <input type="number" min="0" wire:model="form.orden"
                               class="input mono @error('form.orden') input-error @enderror"/>
                        @error('form.orden')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div style="grid-column:1 / -1;">
                        <label class="field-label">{{ __('campos_personalizados.label_etiqueta') }}</label>
                        <input type="text" wire:model="form.etiqueta"
                               class="input @error('form.etiqueta') input-error @enderror"/>
                        @error('form.etiqueta')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="field-label">{{ __('campos_personalizados.label_max_len') }}</label>
                        <input type="number" min="1" wire:model="form.longitud_max"
                               class="input @error('form.longitud_max') input-error @enderror"/>
                        @error('form.longitud_max')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div style="display:flex;align-items:flex-end;gap:14px;">
                        <label style="display:inline-flex;align-items:center;gap:6px;font-size:13px;">
                            <input type="checkbox" wire:model="form.obligatorio" class="checkbox"/>
                            <span>{{ __('campos_personalizados.label_required') }}</span>
                        </label>
                        <label style="display:inline-flex;align-items:center;gap:6px;font-size:13px;">
                            <input type="checkbox" wire:model="form.activo" class="checkbox"/>
                            <span>{{ __('campos_personalizados.label_active') }}</span>
                        </label>
                    </div>

                    <div style="grid-column:1 / -1;border-top:1px solid var(--border);padding-top:10px;">
                        <div class="label-xs" style="margin-bottom:8px;">{{ __('campos_personalizados.advanced_rules') }}</div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                            @if(in_array($form['tipo'] ?? '', ['fecha','fecha_hora'], true))
                                <div>
                                    <label class="field-label">{{ __('campos_personalizados.label_date_min') }}</label>
                                    <select wire:model.live="form.fecha_minima_preset"
                                            class="select @error('form.fecha_minima_preset') input-error @enderror">
                                        <option value="">{{ __('campos_personalizados.no_restriction') }}</option>
                                        <option value="hoy">{{ __('campos_personalizados.today') }}</option>
                                        @if($form['tipo'] === 'fecha_hora')<option value="ahora">{{ __('campos_personalizados.now') }}</option>@endif
                                        <option value="+1d">{{ __('campos_personalizados.plus_1d') }}</option>
                                        <option value="+7d">{{ __('campos_personalizados.plus_7d') }}</option>
                                        <option value="custom">{{ __('campos_personalizados.custom') }}</option>
                                    </select>
                                    @if(($form['fecha_minima_preset'] ?? '') === 'custom')
                                        <input type="text" wire:model="form.fecha_minima_custom"
                                               placeholder="2026-12-31 o -3d"
                                               class="input mono"
                                               style="margin-top:6px;"/>
                                    @endif
                                    @error('form.fecha_minima_preset')<div class="field-error">{{ $message }}</div>@enderror
                                    @error('form.fecha_minima_custom')<div class="field-error">{{ $message }}</div>@enderror
                                </div>
                                <div>
                                    <label class="field-label">{{ __('campos_personalizados.label_date_max') }}</label>
                                    <select wire:model.live="form.fecha_maxima_preset"
                                            class="select @error('form.fecha_maxima_preset') input-error @enderror">
                                        <option value="">{{ __('campos_personalizados.no_restriction') }}</option>
                                        <option value="hoy">{{ __('campos_personalizados.today') }}</option>
                                        @if($form['tipo'] === 'fecha_hora')<option value="ahora">{{ __('campos_personalizados.now') }}</option>@endif
                                        <option value="+1d">{{ __('campos_personalizados.plus_1d') }}</option>
                                        <option value="+7d">{{ __('campos_personalizados.plus_7d') }}</option>
                                        <option value="custom">{{ __('campos_personalizados.custom') }}</option>
                                    </select>
                                    @if(($form['fecha_maxima_preset'] ?? '') === 'custom')
                                        <input type="text" wire:model="form.fecha_maxima_custom"
                                               placeholder="2026-12-31 o +30d"
                                               class="input mono"
                                               style="margin-top:6px;"/>
                                    @endif
                                    @error('form.fecha_maxima_preset')<div class="field-error">{{ $message }}</div>@enderror
                                    @error('form.fecha_maxima_custom')<div class="field-error">{{ $message }}</div>@enderror
                                </div>
                            @endif

                            <div>
                                <label class="field-label">{{ __('campos_personalizados.label_auto_fill') }}</label>
                                <select wire:model="form.auto_fill"
                                        class="select @error('form.auto_fill') input-error @enderror">
                                    <option value="">{{ __('campos_personalizados.no_auto_fill') }}</option>
                                    @if(in_array($form['tipo'] ?? '', ['fecha_hora'], true))
                                        <option value="now">{{ __('campos_personalizados.auto_now') }}</option>
                                    @endif
                                    @if(in_array($form['tipo'] ?? '', ['fecha','fecha_hora'], true))
                                        <option value="today">{{ __('campos_personalizados.auto_today') }}</option>
                                    @endif
                                    @if(in_array($form['tipo'] ?? '', ['texto_corto','texto_largo'], true))
                                        <option value="usuario_nombre">{{ __('campos_personalizados.auto_user_name') }}</option>
                                        <option value="usuario_email">{{ __('campos_personalizados.auto_user_email') }}</option>
                                        <option value="proyecto_codigo">{{ __('campos_personalizados.auto_project_code') }}</option>
                                    @endif
                                </select>
                                @error('form.auto_fill')<div class="field-error">{{ $message }}</div>@enderror
                            </div>

                            <div style="display:flex;align-items:flex-end;">
                                <label style="display:inline-flex;align-items:center;gap:6px;font-size:13px;">
                                    <input type="checkbox" wire:model="form.solo_lectura_tras_guardar" class="checkbox"/>
                                    <span>{{ __('campos_personalizados.readonly_after_save') }}</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="drawer-footer">
                <button type="button" wire:click="cerrarForm" class="btn btn-ghost">{{ __('common.cancel') }}</button>
                <button type="button" wire:click="guardar" class="btn btn-primary">{{ __('campos_personalizados.save_field') }}</button>
            </div>
        </div>
    @endif
</div>
