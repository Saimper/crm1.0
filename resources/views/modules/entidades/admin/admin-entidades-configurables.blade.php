<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">{{ __('entidades.title') }}</h1>
            <div class="page-subtitle">{{ __('entidades.subtitle') }}</div>
        </div>
        <div style="display:flex;gap:8px;">
            <a href="{{ route('admin.dashboard') }}" wire:navigate class="btn btn-ghost btn-sm">{{ __('entidades.back_to_panel') }}</a>
            <button type="button" wire:click="abrirFormCrear" class="btn btn-primary">
                <x-ui.icon name="plus" :size="14" />
                {{ __('entidades.new_entity') }}
            </button>
        </div>
    </div>

    @if(session('entidades-ok'))
        <div class="alert alert-success" style="margin-bottom:14px;">{{ session('entidades-ok') }}</div>
    @endif

    <div class="card card-pad" style="margin-bottom:14px;">
        <div style="display:flex;align-items:flex-end;gap:14px;">
            <div class="field" style="flex:1;max-width:360px;margin-bottom:0;">
                <label class="field-label">{{ __('entidades.label_project') }}</label>
                <select wire:model.live="proyectoSeleccionadoId" class="select">
                    @foreach($proyectos as $p)
                        <option value="{{ $p->id }}">{{ $p->codigo }} — {{ $p->nombre }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:260px 1fr;gap:14px;">
        {{-- Sidebar selector --}}
        <div class="card" style="padding:0;">
            <div style="padding:10px 12px;border-bottom:1px solid var(--border);font-size:12px;font-weight:500;color:var(--text-tertiary);text-transform:uppercase;letter-spacing:0.06em;">
                {{ __('entidades.sidebar_header') }}
            </div>
            @if($entidades->isEmpty())
                <div style="padding:16px;font-size:12px;color:var(--text-tertiary);">{{ __('entidades.empty_entities') }}</div>
            @else
                @foreach($entidades as $e)
                    <button type="button" wire:key="ent-{{ $e->id }}"
                            wire:click="abrirCamposDe({{ $e->id }})"
                            class="sb-item {{ $entidadConCamposAbiertosId === $e->id ? 'active' : '' }}"
                            style="height:auto;padding:10px 14px;text-align:left;">
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:13px;font-weight:500;">{{ $e->nombre }}</div>
                            <div style="font-size:11px;color:var(--text-tertiary);margin-top:2px;">
                                <span class="font-mono">{{ $e->codigo }}</span> · {{ $e->relacion_con }}
                                @if($e->cartera_nombre) · {{ $e->cartera_nombre }} @endif
                            </div>
                        </div>
                    </button>
                @endforeach
            @endif
        </div>

        {{-- Main detail --}}
        <div>
            @if($formVisible)
                <div class="card card-pad" style="margin-bottom:14px;border-color:var(--primary-soft-border);background:var(--primary-soft);">
                    <div style="font-size:13px;font-weight:600;color:var(--primary-text);margin-bottom:12px;">
                        {{ $entidadEditandoId === null ? __('entidades.form_create') : __('entidades.form_edit') }}
                    </div>
                    <form wire:submit.prevent="guardarEntidad" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;">
                        <div class="field">
                            <label class="field-label">{{ __('entidades.label_code') }}</label>
                            <input type="text" wire:model="formCodigo" placeholder="POLIZAS"
                                   class="input mono uppercase @error('formCodigo') input-error @enderror"/>
                            @error('formCodigo')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="field">
                            <label class="field-label">{{ __('entidades.label_name') }}</label>
                            <input type="text" wire:model="formNombre" :placeholder="__('entidades.placeholder_nombre')"
                                   class="input @error('formNombre') input-error @enderror"/>
                            @error('formNombre')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="field">
                            <label class="field-label">{{ __('entidades.label_icon') }}</label>
                            <input type="text" wire:model="formIcono" class="input" placeholder="file-text"/>
                        </div>
                        <div class="field">
                            <label class="field-label">{{ __('entidades.label_relation') }}</label>
                            <select wire:model="formRelacion" class="select">
                                <option value="ninguna">{{ __('entidades.rel_none') }}</option>
                                <option value="caso">{{ __('entidades.rel_case') }}</option>
                                <option value="persona">{{ __('entidades.rel_person') }}</option>
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label">{{ __('entidades.label_portfolio') }}</label>
                            <select wire:model="formCarteraId" class="select">
                                <option value="">{{ __('entidades.all_portfolios') }}</option>
                                @foreach($carterasDelProyecto as $c)
                                    <option value="{{ $c->id }}">{{ $c->codigo }} — {{ $c->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label">{{ __('entidades.label_active') }}</label>
                            <select wire:model="formActivo" class="select">
                                <option value="1">{{ __('entidades.yes') }}</option>
                                <option value="0">{{ __('entidades.no') }}</option>
                            </select>
                        </div>
                        <div class="field" style="grid-column:1 / -1;">
                            <label class="field-label">{{ __('entidades.label_description') }}</label>
                            <textarea wire:model="formDescripcion" rows="2" class="textarea"></textarea>
                        </div>
                        <div style="grid-column:1 / -1;display:flex;justify-content:flex-end;gap:8px;">
                            <button type="button" wire:click="cerrarForm" class="btn btn-ghost">{{ __('common.cancel') }}</button>
                            <button type="submit" class="btn btn-primary">{{ __('common.save') }}</button>
                        </div>
                    </form>
                </div>
            @endif

            @if($entidadConCamposAbiertosId !== null)
                @php
                    $entidadActiva = $entidades->firstWhere('id', $entidadConCamposAbiertosId);
                @endphp
                <div class="card" style="padding:16px;margin-bottom:14px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                        <div>
                            <div style="font-size:13px;font-weight:600;">{{ __('entidades.fields_title') }}</div>
                            @if($entidadActiva)
                                <div style="font-size:11px;color:var(--text-tertiary);margin-top:2px;">
                                    <span class="font-mono">{{ $entidadActiva->codigo }}</span> · {{ $entidadActiva->nombre }}
                                </div>
                            @endif
                        </div>
                        <div style="display:flex;gap:6px;">
                            @if(! $formCampoVisible)
                                <button type="button" wire:click="abrirFormCampoCrear" class="btn btn-secondary btn-sm">
                                    <x-ui.icon name="plus" :size="12" />
                                    {{ __('entidades.add_field') }}
                                </button>
                            @endif
                            @if($entidadActiva)
                                <button type="button" wire:click="abrirFormEditar({{ $entidadActiva->id }})" class="btn btn-ghost btn-sm">
                                    <x-ui.icon name="edit" :size="12" />
                                    {{ __('entidades.edit_entity_btn') }}
                                </button>
                                <button type="button" wire:click="eliminarEntidad({{ $entidadActiva->id }})"
                                        wire:confirm="{{ __('entidades.confirm_deactivate_entity') }}"
                                        class="btn btn-ghost btn-sm" style="color:var(--danger-text);">{{ __('entidades.deactivate_entity') }}</button>
                            @endif
                            <button type="button" wire:click="cerrarCampos" class="btn btn-ghost btn-sm">
                                <x-ui.icon name="x" :size="12" />
                            </button>
                        </div>
                    </div>

                    @if($formCampoVisible)
                        <form wire:submit.prevent="guardarCampo"
                              style="display:grid;grid-template-columns:repeat(5,1fr);gap:8px;background:var(--bg-subtle);padding:12px;border-radius:6px;border:1px solid var(--border);margin-bottom:14px;">
                            <div class="field" style="margin-bottom:0;">
                                <label class="field-label">{{ __('entidades.field_code') }}</label>
                                <input type="text" wire:model="formCampoCodigo" placeholder="numero_poliza"
                                       class="input input-sm mono @error('formCampoCodigo') input-error @enderror"/>
                                @error('formCampoCodigo')<div class="field-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="field" style="margin-bottom:0;">
                                <label class="field-label">{{ __('entidades.field_label') }}</label>
                                <input type="text" wire:model="formCampoEtiqueta"
                                       class="input input-sm @error('formCampoEtiqueta') input-error @enderror"/>
                                @error('formCampoEtiqueta')<div class="field-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="field" style="margin-bottom:0;">
                                <label class="field-label">{{ __('entidades.field_type') }}</label>
                                <select wire:model="formCampoTipo" class="select input-sm">
                                    <option value="texto_corto">{{ __('entidades.type_short_text') }}</option>
                                    <option value="texto_largo">{{ __('entidades.type_long_text') }}</option>
                                    <option value="numero_entero">{{ __('entidades.type_integer') }}</option>
                                    <option value="numero_decimal">{{ __('entidades.type_decimal') }}</option>
                                    <option value="fecha">{{ __('entidades.type_date') }}</option>
                                    <option value="fecha_hora">{{ __('entidades.type_datetime') }}</option>
                                    <option value="booleano">{{ __('entidades.type_boolean') }}</option>
                                    <option value="moneda">{{ __('entidades.type_currency') }}</option>
                                </select>
                            </div>
                            <div class="field" style="margin-bottom:0;">
                                <label class="field-label">{{ __('entidades.field_order') }}</label>
                                <input type="number" wire:model="formCampoOrden" min="0" class="input input-sm mono"/>
                            </div>
                            <div style="display:flex;align-items:flex-end;gap:6px;">
                                <label style="display:inline-flex;align-items:center;gap:6px;font-size:12px;">
                                    <input type="checkbox" wire:model="formCampoObligatorio" class="checkbox"/>
                                    {{ __('entidades.field_required') }}
                                </label>
                            </div>
                            <div style="grid-column:1 / -1;display:flex;justify-content:flex-end;gap:6px;">
                                <button type="button" wire:click="cerrarFormCampo" class="btn btn-ghost btn-sm">{{ __('common.cancel') }}</button>
                                <button type="submit" class="btn btn-primary btn-sm">{{ __('entidades.save_field') }}</button>
                            </div>
                        </form>
                    @endif

                    @if($campos->isEmpty())
                        <div class="empty" style="padding:24px;">
                            <div class="empty-desc">{{ __('entidades.empty_fields') }}</div>
                        </div>
                    @else
                        <table class="table table-compact">
                            <thead>
                                <tr>
                                    <th style="width:160px;">{{ __('entidades.col_code') }}</th>
                                    <th>{{ __('entidades.col_label') }}</th>
                                    <th style="width:130px;">{{ __('entidades.col_type') }}</th>
                                    <th style="width:90px;">{{ __('entidades.col_required') }}</th>
                                    <th class="num" style="width:80px;">{{ __('entidades.col_order') }}</th>
                                    <th style="width:110px;">{{ __('entidades.col_status') }}</th>
                                    <th style="width:80px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($campos as $c)
                                    <tr wire:key="campoent-{{ $c->id }}">
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
                                                {{ $c->activo ? __('entidades.status_active') : __('entidades.status_inactive') }}
                                            </span>
                                        </td>
                                        <td>
                                            <div style="display:flex;gap:2px;">
                                                <button type="button" wire:click="abrirFormCampoEditar({{ $c->id }})" class="icon-btn" :title="__('common.edit')">
                                                    <x-ui.icon name="edit" :size="12" />
                                                </button>
                                                @if($c->activo)
                                                    <button type="button" wire:click="desactivarCampo({{ $c->id }})"
                                                            class="icon-btn" style="color:var(--danger-text);" :title="__('entidades.title_deactivate')">
                                                        <x-ui.icon name="trash" :size="12" />
                                                    </button>
                                                @else
                                                    <button type="button" wire:click="activarCampo({{ $c->id }})"
                                                            class="icon-btn" style="color:var(--success-text);" :title="__('entidades.title_activate')">
                                                        <x-ui.icon name="check" :size="12" />
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            @else
                <div class="card card-pad">
                    <div class="empty" style="padding:24px;">
                        <div class="empty-icon"><x-ui.icon name="layers" :size="32" /></div>
                        <div class="empty-title">{{ __('entidades.select_entity') }}</div>
                        <div class="empty-desc">{{ __('entidades.select_entity_desc') }}</div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
