<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">{{ __('tenancy.proyectos_title') }}</h1>
            <div class="page-subtitle">{{ __('tenancy.proyectos_subtitle') }}</div>
        </div>
        <div class="flex items-start gap-2">
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

    <div class="card">
        <x-ui.toolbar :count="__('tenancy.records_count', ['count' => $proyectos->count()])">
            <x-ui.search-input wire:model.live.debounce.300ms="busqueda" :placeholder="__('common.search')" />
            <select wire:model.live="filtroTipo" class="select" style="width:160px;">
                <option value="">{{ __('tenancy.filter_all_types') }}</option>
                <option value="cobranza">{{ __('tenancy.filter_cobranza') }}</option>
                <option value="cx">{{ __('tenancy.filter_cx') }}</option>
                <option value="venta">{{ __('tenancy.filter_venta') }}</option>
                <option value="servicio">{{ __('tenancy.filter_servicio') }}</option>
            </select>
        </x-ui.toolbar>

        {{-- La lista de antes se queda en pantalla mientras llega la nueva; sólo
             esta barra dice que se está trabajando. --}}
        <x-ui.cargando />

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
                        @unless($dentroDeUnCliente)
                            <th style="width:200px;">{{ __('tenancy.col_mandante') }}</th>
                        @endunless
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
                        {{-- El clic en la fila ENTRA al proyecto: es lo que promete el
                             chevron de la derecha y lo que espera cualquiera. Editar y
                             configurar son acciones aparte, cada una con su icono. --}}
                        <tr wire:key="proyecto-{{ $p->id }}"
                            onclick="window.location='{{ route('proyectos.dashboard', $p->id) }}'">
                            <td><span class="font-mono text-sm">{{ $p->codigo }}</span></td>
                            <td><span class="font-medium">{{ $p->nombre }}</span></td>
                            @unless($dentroDeUnCliente)
                                <td>
                                    <div class="text-base">{{ $p->mandante_codigo }}</div>
                                    <div class="text-xs text-ink-500">{{ $p->mandante_nombre }}</div>
                                </td>
                            @endunless
                            <td><span class="badge {{ $tipoBadge }}">{{ $p->tipo_operacion }}</span></td>
                            <td class="num">{{ $p->total_carteras }}</td>
                            <td>
                                <span style="display:inline-flex;align-items:center;gap:6px;">
                                    <span class="dot dot-{{ $p->activo ? 'success' : 'neutral' }}"></span>
                                    {{ $p->activo ? __('tenancy.status_active') : __('tenancy.status_inactive') }}
                                </span>
                            </td>
                            <td onclick="event.stopPropagation()" style="white-space:nowrap;">
                                <span style="display:inline-flex;align-items:center;gap:2px;">
                                    <button type="button" class="icon-btn"
                                            wire:click.stop="abrirFormEditar({{ $p->id }})"
                                            title="{{ __('tenancy.accion_editar') }}"
                                            aria-label="{{ __('tenancy.accion_editar') }}">
                                        <x-ui.icon name="pencil" :size="14" />
                                    </button>
                                    <a class="icon-btn" href="{{ route('admin.proyectos.configurar', $p->public_id) }}"
                                       wire:navigate
                                       title="{{ __('tenancy.accion_configurar') }}"
                                       aria-label="{{ __('tenancy.accion_configurar') }}">
                                        <x-ui.icon name="settings" :size="14" />
                                    </a>
                                    <x-ui.icon name="chevron-right" :size="14" class="text-ink-400" style="margin-left:4px;" />
                                </span>
                            </td>
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
                <div class="text-md font-semibold">
                    {{ $editandoId === null ? __('tenancy.drawer_new_proyecto') : __('tenancy.drawer_edit_proyecto') }}
                </div>
                <button type="button" wire:click="cerrarForm" class="icon-btn" aria-label="{{ __('tenancy.close') }}">
                    <x-ui.icon name="x" :size="14" />
                </button>
            </div>
            <div class="drawer-body">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                    <div style="grid-column:1 / -1;">
                        <label class="field-label">
                            {{ __('tenancy.label_mandante') }}
                            @if($editandoId !== null)
                                <span class="text-ink-500" style="font-weight:400;">{{ __('tenancy.type_locked') }}</span>
                            @endif
                        </label>
                        {{-- Al editar, el cliente se muestra pero no se cambia: mover un
                             proyecto de empresa arrastra sus casos, personas y carteras,
                             que cuelgan de proyecto_id. Mismo trato que tipo_operacion. --}}
                        @if($editandoId !== null)
                            <div class="flex items-center gap-2" style="height:36px;padding:0 10px;background:var(--bg-subtle);border:1px solid var(--border);border-radius:6px;color:var(--text-secondary);">
                                <span class="badge badge-neutral">{{ $mandanteEnEdicion?->codigo ?? '—' }}</span>
                                <span class="text-sm">{{ $mandanteEnEdicion?->nombre }}</span>
                                <span class="text-xs text-ink-500" style="margin-left:auto;">{{ __('tenancy.not_editable') }}</span>
                            </div>
                        @else
                            <select wire:model="form.mandante_id" class="select @error('form.mandante_id') input-error @enderror">
                                <option value="">—</option>
                                @foreach($mandantes as $m)
                                    <option value="{{ $m->id }}">{{ $m->codigo }} — {{ $m->nombre }}</option>
                                @endforeach
                            </select>
                        @endif
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
                                <span class="text-ink-500" style="font-weight:400;">{{ __('tenancy.type_locked') }}</span>
                            @endif
                        </label>
                        @if($editandoId !== null)
                            <div class="flex items-center gap-2" style="height:36px;padding:0 10px;background:var(--bg-subtle);border:1px solid var(--border);border-radius:6px;color:var(--text-secondary);">
                                <span class="badge badge-neutral">{{ $form['tipo_operacion'] }}</span>
                                <span class="text-xs text-ink-500" style="margin-left:auto;" title="{{ __('tenancy.not_editable') }}">{{ __('tenancy.not_editable') }}</span>
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
                    {{-- Quién se queda la cuenta cuando un asesor la gestiona. Va
                         apagado por defecto: hay operaciones donde repartir es
                         decisión del supervisor y sólo suya. --}}
                    <div class="field-block">
                        <label class="flex items-start" style="gap:9px;cursor:pointer;">
                            <input type="checkbox" wire:model="form.permite_autoasignacion" style="margin-top:2px;"/>
                            <span>
                                <span class="text-base">{{ __('tenancy.autoasignacion') }}</span>
                                <span class="text-sm text-ink-500" style="display:block;margin-top:2px;">{{ __('tenancy.autoasignacion_ayuda') }}</span>
                            </span>
                        </label>
                    </div>
                    {{-- Los dos botones de abajo se parecen y no hacen lo mismo.
                         Aquí se dice cuál es cuál, antes de pulsarlos. --}}
                    @if($proyectoEnEdicion !== null)
                        <div class="field-block">
                            <div class="text-base font-medium">{{ __('tenancy.baja_proyecto_titulo') }}</div>
                            <div class="text-sm text-ink-500 mt-1">
                                <span class="font-medium">{{ __('tenancy.btn_deactivate') }}</span> — {{ __('tenancy.deactivate_hint_proyecto') }}
                            </div>
                            <div class="text-sm text-ink-500 mt-0.5">
                                <span class="font-medium">{{ __('tenancy.btn_archive') }}</span> — {{ __('tenancy.archive_hint_proyecto') }}
                            </div>
                        </div>
                    @endif
                </div>
            </div>
            <div class="drawer-footer">
                @if($proyectoEnEdicion !== null)
                    <span class="drawer-footer-start">
                        @if($proyectoEnEdicion->activo)
                            <button type="button" wire:click="desactivar({{ $editandoId }})"
                                    wire:confirm="{{ __('tenancy.confirm_deactivate_proyecto') }}"
                                    class="btn btn-ghost btn-ghost-danger">{{ __('tenancy.btn_deactivate') }}</button>
                        @else
                            <button type="button" wire:click="activar({{ $editandoId }})"
                                    class="btn btn-ghost btn-ghost-success">{{ __('tenancy.btn_activate') }}</button>
                        @endif
                        <button type="button" wire:click="archivar({{ $editandoId }})"
                                wire:confirm="{{ __('tenancy.confirm_archive_proyecto') }}"
                                class="btn btn-ghost btn-ghost-danger">{{ __('tenancy.btn_archive') }}</button>
                    </span>
                @endif
                <button type="button" wire:click="cerrarForm" class="btn btn-ghost">{{ __('common.cancel') }}</button>
                <button type="button" wire:click="guardar" class="btn btn-primary">{{ __('common.save') }}</button>
            </div>
        </div>
    @endif
</div>
