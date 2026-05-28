<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">{{ __('tenancy.mandantes_title') }}</h1>
            <div class="page-subtitle">{{ __('tenancy.mandantes_subtitle') }}</div>
        </div>
        <div style="display:flex;gap:8px;">
            <a href="{{ route('admin.dashboard') }}" wire:navigate class="btn btn-ghost btn-sm">{{ __('tenancy.back_to_panel') }}</a>
            <button type="button" wire:click="abrirFormCrear" class="btn btn-primary">
                <x-ui.icon name="plus" :size="14" />
                {{ __('tenancy.new_mandante') }}
            </button>
        </div>
    </div>

    @if(session('admin-mandantes-ok'))
        <div class="alert alert-success" style="margin-bottom:14px;">{{ session('admin-mandantes-ok') }}</div>
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
            <span style="flex:1;"></span>
            <span style="font-size:12px;color:var(--text-tertiary);">{{ __('tenancy.records_count', ['count' => $mandantes->count()]) }}</span>
        </div>

        @if($mandantes->isEmpty())
            <div class="empty">
                <div class="empty-icon"><x-ui.icon name="building" :size="32" /></div>
                <div class="empty-title">{{ __('tenancy.empty_mandantes') }}</div>
                <div class="empty-desc">{{ __('tenancy.empty_mandantes_desc') }}</div>
            </div>
        @else
            <table class="table table-compact table-clickable">
                <thead>
                    <tr>
                        <th style="width:120px;">{{ __('tenancy.col_code') }}</th>
                        <th>{{ __('tenancy.col_name') }}</th>
                        <th style="width:160px;">{{ __('tenancy.col_document') }}</th>
                        <th class="num" style="width:100px;">{{ __('tenancy.col_projects') }}</th>
                        <th style="width:110px;">{{ __('tenancy.col_status') }}</th>
                        <th style="width:60px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($mandantes as $m)
                        <tr wire:key="mandante-{{ $m->id }}" wire:click="abrirFormEditar({{ $m->id }})">
                            <td><span class="font-mono" style="font-size:12px;">{{ $m->codigo }}</span></td>
                            <td><span style="font-weight:500;">{{ $m->nombre }}</span></td>
                            <td><span class="font-mono" style="font-size:12px;color:var(--text-secondary);">{{ $m->documento ?? '—' }}</span></td>
                            <td class="num">{{ $m->total_proyectos }}</td>
                            <td>
                                <span style="display:inline-flex;align-items:center;gap:6px;">
                                    <span class="dot dot-{{ $m->activo ? 'success' : 'neutral' }}"></span>
                                    {{ $m->activo ? __('tenancy.status_active') : __('tenancy.status_inactive') }}
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
        <div class="scrim" wire:click="cerrarForm" wire:key="form-mandante-scrim"></div>
        <div class="drawer" wire:key="form-mandante">
            <div class="drawer-header">
                <div style="font-size:14px;font-weight:600;">
                    {{ $editandoId === null ? __('tenancy.drawer_new_mandante') : __('tenancy.drawer_edit_mandante') }}
                </div>
                <button type="button" wire:click="cerrarForm" class="icon-btn" :aria-label="__('tenancy.close')">
                    <x-ui.icon name="x" :size="14" />
                </button>
            </div>
            <div class="drawer-body">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                    <div>
                        <label class="field-label">{{ __('tenancy.label_code') }}</label>
                        <input type="text" wire:model="form.codigo" placeholder="BANCO_X"
                               class="input mono uppercase @error('form.codigo') input-error @enderror"/>
                        @error('form.codigo')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="field-label">{{ __('tenancy.label_document') }}</label>
                        <input type="text" wire:model="form.documento"
                               class="input mono @error('form.documento') input-error @enderror"/>
                        @error('form.documento')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div style="grid-column:1 / -1;">
                        <label class="field-label">{{ __('tenancy.label_razon_social') }}</label>
                        <input type="text" wire:model="form.nombre"
                               class="input @error('form.nombre') input-error @enderror"/>
                        @error('form.nombre')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
            <div class="drawer-footer">
                @if($editandoId !== null)
                    @php
                        $row = \App\Modules\Tenancy\Infrastructure\Persistence\Models\MandanteModel::query()->find($editandoId);
                    @endphp
                    @if($row && $row->activo)
                        <button type="button" wire:click="desactivar({{ $editandoId }})"
                                wire:confirm="{{ __('tenancy.confirm_deactivate_mandante') }}"
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
