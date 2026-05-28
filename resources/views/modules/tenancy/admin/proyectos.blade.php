<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">{{ __('tenancy.proyectos_title') }}</h1>
            <div class="page-subtitle">{{ __('tenancy.proyectos_subtitle') }}</div>
        </div>
        <div style="display:flex;gap:8px;">
            <a href="{{ route('admin.dashboard') }}" wire:navigate class="btn btn-ghost btn-sm">{{ __('tenancy.back_to_panel') }}</a>
            <button type="button" wire:click="abrirFormCrear" class="btn btn-primary">
                <x-ui.icon name="plus" :size="14" />
                {{ __('tenancy.new_proyecto') }}
            </button>
        </div>
    </div>

    @if(session('admin-proyectos-ok'))
        <div class="alert alert-success" style="margin-bottom:14px;">{{ session('admin-proyectos-ok') }}</div>
    @endif

    <div class="card" style="padding:0;">
        <div style="padding:12px 16px;border-bottom:1px solid var(--border);display:flex;gap:10px;align-items:center;">
            <div style="position:relative;width:280px;">
                <span style="position:absolute;left:9px;top:11px;color:var(--text-muted);pointer-events:none;">
                    <x-ui.icon name="search" :size="13" />
                </span>
                <input type="text" wire:model.live.debounce.300ms="busqueda"
                       class="input" :placeholder="__('common.search')" style="padding-left:28px;"/>
            </div>
            <select wire:model.live="filtroTipo" class="select" style="width:160px;">
                <option value="">{{ __('tenancy.filter_all_types') }}</option>
                <option value="cobranza">{{ __('tenancy.filter_cobranza') }}</option>
                <option value="cx">{{ __('tenancy.filter_cx') }}</option>
                <option value="venta">{{ __('tenancy.filter_venta') }}</option>
                <option value="servicio">{{ __('tenancy.filter_servicio') }}</option>
            </select>
            <span style="flex:1;"></span>
            <span style="font-size:12px;color:var(--text-tertiary);">{{ __('tenancy.records_count', ['count' => $proyectos->count()]) }}</span>
        </div>

        @if($proyectos->isEmpty())
            <div class="empty">
                <div class="empty-icon"><x-ui.icon name="folder" :size="32" /></div>
                <div class="empty-title">{{ __('tenancy.empty_proyectos') }}</div>
                <div class="empty-desc">{{ __('tenancy.empty_proyectos_desc') }}</div>
            </div>
        @else
            <table class="table table-compact table-clickable">
                <thead>
                    <tr>
                        <th style="width:120px;">{{ __('tenancy.col_code') }}</th>
                        <th>{{ __('tenancy.col_name') }}</th>
                        <th style="width:200px;">{{ __('tenancy.col_mandante') }}</th>
                        <th style="width:110px;">{{ __('tenancy.col_type') }}</th>
                        <th class="num" style="width:100px;">{{ __('tenancy.col_portfolios') }}</th>
                        <th style="width:110px;">{{ __('tenancy.col_status') }}</th>
                        <th style="width:60px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($proyectos as $p)
                        @php
                            $tipoBadge = match ($p->tipo_operacion) {
                                'cobranza' => 'badge-warning',
                                'cx'       => 'badge-info',
                                'venta'    => 'badge-success',
                                'servicio' => 'badge-primary',
                                default    => 'badge-neutral',
                            };
                        @endphp
                        <tr wire:key="proyecto-{{ $p->id }}" wire:click="abrirFormEditar({{ $p->id }})">
                            <td><span class="font-mono" style="font-size:12px;">{{ $p->codigo }}</span></td>
                            <td><span style="font-weight:500;">{{ $p->nombre }}</span></td>
                            <td>
                                <div style="font-size:13px;color:var(--text);">{{ $p->mandante_codigo }}</div>
                                <div style="font-size:11px;color:var(--text-tertiary);">{{ $p->mandante_nombre }}</div>
                            </td>
                            <td><span class="badge {{ $tipoBadge }}">{{ $p->tipo_operacion }}</span></td>
                            <td class="num">{{ $p->total_carteras }}</td>
                            <td>
                                <span style="display:inline-flex;align-items:center;gap:6px;">
                                    <span class="dot dot-{{ $p->activo ? 'success' : 'neutral' }}"></span>
                                    {{ $p->activo ? __('tenancy.status_active') : __('tenancy.status_inactive') }}
                                </span>
                            </td>
                            <td><x-ui.icon name="chevron-right" :size="14" style="color:var(--text-muted);" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @if($formVisible)
        <div class="scrim" wire:click="cerrarForm" wire:key="form-proyecto-scrim"></div>
        <div class="drawer" wire:key="form-proyecto">
            <div class="drawer-header">
                <div style="font-size:14px;font-weight:600;">
                    {{ $editandoId === null ? __('tenancy.drawer_new_proyecto') : __('tenancy.drawer_edit_proyecto') }}
                </div>
                <button type="button" wire:click="cerrarForm" class="icon-btn" :aria-label="__('tenancy.close')">
                    <x-ui.icon name="x" :size="14" />
                </button>
            </div>
            <div class="drawer-body">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                    <div style="grid-column:1 / -1;">
                        <label class="field-label">{{ __('tenancy.label_mandante') }}</label>
                        <select wire:model="form.mandante_id" class="select @error('form.mandante_id') input-error @enderror">
                            <option value="">—</option>
                            @foreach($mandantes as $m)
                                <option value="{{ $m->id }}">{{ $m->codigo }} — {{ $m->nombre }}</option>
                            @endforeach
                        </select>
                        @error('form.mandante_id')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="field-label">{{ __('tenancy.label_code') }}</label>
                        <input type="text" wire:model="form.codigo" placeholder="COBRANZA_2026"
                               class="input mono uppercase @error('form.codigo') input-error @enderror"/>
                        @error('form.codigo')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="field-label">
                            {{ __('tenancy.label_type') }}
                            @if($editandoId !== null)
                                <span style="color:var(--text-tertiary);font-weight:400;">{{ __('tenancy.type_locked') }}</span>
                            @endif
                        </label>
                        @if($editandoId !== null)
                            <div style="display:flex;align-items:center;gap:8px;height:36px;padding:0 10px;background:var(--bg-subtle);border:1px solid var(--border);border-radius:6px;color:var(--text-secondary);">
                                <span class="badge badge-neutral">{{ $form['tipo_operacion'] }}</span>
                                <span style="font-size:11px;color:var(--text-tertiary);margin-left:auto;" :title="__('tenancy.not_editable')">{{ __('tenancy.not_editable') }}</span>
                            </div>
                        @else
                            <select wire:model="form.tipo_operacion"
                                    class="select @error('form.tipo_operacion') input-error @enderror">
                                <option value="cobranza">{{ __('tenancy.type_cobranza') }}</option>
                                <option value="cx">{{ __('tenancy.type_cx') }}</option>
                                <option value="venta">{{ __('tenancy.type_venta') }}</option>
                                <option value="servicio">{{ __('tenancy.type_servicio') }}</option>
                            </select>
                            @error('form.tipo_operacion')<div class="field-error">{{ $message }}</div>@enderror
                        @endif
                    </div>
                    <div style="grid-column:1 / -1;">
                        <label class="field-label">{{ __('tenancy.label_name') }}</label>
                        <input type="text" wire:model="form.nombre"
                               class="input @error('form.nombre') input-error @enderror"/>
                        @error('form.nombre')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div style="grid-column:1 / -1;">
                        <label class="field-label">{{ __('tenancy.label_description') }}</label>
                        <textarea wire:model="form.descripcion" rows="2"
                                  class="textarea @error('form.descripcion') input-error @enderror"></textarea>
                        @error('form.descripcion')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="field-label">{{ __('tenancy.label_start_date') }}</label>
                        <input type="date" wire:model="form.fecha_inicio"
                               class="input @error('form.fecha_inicio') input-error @enderror"/>
                        @error('form.fecha_inicio')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="field-label">{{ __('tenancy.label_end_date') }}</label>
                        <input type="date" wire:model="form.fecha_fin"
                               class="input @error('form.fecha_fin') input-error @enderror"/>
                        @error('form.fecha_fin')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
            <div class="drawer-footer">
                @if($editandoId !== null)
                    @php
                        $row = \App\Modules\Tenancy\Infrastructure\Persistence\Models\ProyectoModel::query()->find($editandoId);
                    @endphp
                    @if($row && $row->activo)
                        <button type="button" wire:click="desactivar({{ $editandoId }})"
                                wire:confirm="{{ __('tenancy.confirm_deactivate_proyecto') }}"
                                class="btn btn-ghost" style="color:var(--danger-text);margin-right:auto;">{{ __('tenancy.btn_deactivate') }}</button>
                    @elseif($row)
                        <button type="button" wire:click="activar({{ $editandoId }})"
                                class="btn btn-ghost" style="color:var(--success-text);margin-right:auto;">{{ __('tenancy.btn_activate') }}</button>
                    @endif
                @endif
                <button type="button" wire:click="cerrarForm" class="btn btn-ghost">{{ __('common.cancel') }}</button>
                <button type="button" wire:click="guardar" class="btn btn-primary">{{ __('common.save') }}</button>
            </div>
        </div>
    @endif
</div>
