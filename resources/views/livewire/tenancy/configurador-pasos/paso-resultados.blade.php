<div>
    @if(session('paso-resultados-ok'))
        <div class="alert alert-success" style="margin-bottom:14px;">{{ session('paso-resultados-ok') }}</div>
    @endif
    @if(session('paso-resultados-error'))
        <div class="alert alert-warning" style="margin-bottom:14px;">{{ session('paso-resultados-error') }}</div>
    @endif

    <div class="card" style="padding:0;">
        <div style="padding:12px 16px;border-bottom:1px solid var(--border);display:flex;gap:10px;align-items:center;">
            <div style="position:relative;width:280px;">
                <span style="position:absolute;left:9px;top:11px;color:var(--text-muted);pointer-events:none;">
                    <x-ui.icon name="search" :size="13" />
                </span>
                <input type="text" wire:model.live.debounce.300ms="busqueda"
                       class="input" placeholder="{{ __('common.search') }}…" style="padding-left:28px;"/>
            </div>
            <span style="flex:1;"></span>
            <span style="font-size:12px;color:var(--text-tertiary);">{{ __('configurador.resultados.n_resultados', ['n' => $resultados->count()]) }}</span>
            <button type="button" wire:click="abrirFormCrear" class="btn btn-primary">
                <x-ui.icon name="plus" :size="14" />
                <span>{{ __('configurador.resultados.nuevo') }}</span>
            </button>
        </div>

        @if($resultados->isEmpty())
            <div class="empty">
                <div class="empty-icon"><x-ui.icon name="folder" :size="32" /></div>
                <div class="empty-title">{{ __('configurador.resultados.sin_titulo') }}</div>
                <div class="empty-desc">{{ __('configurador.resultados.sin_desc') }}</div>
            </div>
        @else
            <table class="table table-compact table-clickable">
                <thead>
                    <tr>
                        <th style="width:160px;">{{ __('configurador.campo_codigo') }}</th>
                        <th>{{ __('common.name') }}</th>
                        <th style="width:90px;">{{ __('configurador.resultados.col_compromiso') }}</th>
                        <th style="width:70px;">{{ __('configurador.resultados.col_causa') }}</th>
                        <th style="width:90px;">{{ __('configurador.resultados.col_contacto_efectivo') }}</th>
                        <th class="num" style="width:70px;">{{ __('configurador.campo_orden') }}</th>
                        <th style="width:110px;">{{ __('configurador.campo_estado') }}</th>
                        <th style="width:60px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($resultados as $r)
                        <tr wire:key="paso-resultado-{{ $r->id }}" wire:click="abrirFormEditar({{ $r->id }})">
                            <td><span class="font-mono" style="font-size:12px;">{{ $r->codigo }}</span></td>
                            <td><span style="font-weight:500;">{{ $r->nombre }}</span></td>
                            <td>
                                @if($r->requiere_compromiso)
                                    <span class="badge badge-warning">{{ __('configurador.resultados.si') }}</span>
                                @else
                                    <span style="font-size:12px;color:var(--text-muted);">—</span>
                                @endif
                            </td>
                            <td>
                                @if($r->requiere_causa)
                                    <span class="badge badge-warning">{{ __('configurador.resultados.si') }}</span>
                                @else
                                    <span style="font-size:12px;color:var(--text-muted);">—</span>
                                @endif
                            </td>
                            <td>
                                @if($r->es_contacto_efectivo)
                                    <span class="badge badge-success">{{ __('configurador.resultados.si') }}</span>
                                @else
                                    <span style="font-size:12px;color:var(--text-muted);">—</span>
                                @endif
                            </td>
                            <td class="num">{{ $r->orden }}</td>
                            <td>
                                <span style="display:inline-flex;align-items:center;gap:6px;">
                                    <span class="dot dot-{{ $r->activo ? 'success' : 'neutral' }}"></span>
                                    {{ $r->activo ? __('configurador.activo') : __('configurador.inactivo') }}
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
        <div class="scrim" wire:click="cerrarForm" wire:key="paso-resultado-scrim"></div>
        <div class="drawer" wire:key="paso-resultado-drawer">
            <div class="drawer-header">
                <div style="font-size:14px;font-weight:600;">
                    {{ $editandoId === null ? __('configurador.resultados.drawer_nuevo') : __('configurador.resultados.drawer_editar') }}
                </div>
                <button type="button" wire:click="cerrarForm" class="icon-btn" aria-label="{{ __('configurador.cerrar') }}">
                    <x-ui.icon name="x" :size="14" />
                </button>
            </div>
            <div class="drawer-body">
                <div style="display:grid;grid-template-columns:1fr;gap:14px;">
                    <div>
                        <label class="field-label">{{ __('configurador.campo_codigo') }}</label>
                        <input type="text" wire:model="form.codigo" placeholder="CONTACTO_EFECTIVO" maxlength="50"
                               class="input mono uppercase @error('form.codigo') input-error @enderror"/>
                        @error('form.codigo')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="field-label">{{ __('common.name') }}</label>
                        <input type="text" wire:model="form.nombre" maxlength="150"
                               class="input @error('form.nombre') input-error @enderror"/>
                        @error('form.nombre')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="field-label">{{ __('configurador.campo_descripcion') }}</label>
                        <textarea wire:model="form.descripcion" rows="3" maxlength="500"
                                  class="input @error('form.descripcion') input-error @enderror"></textarea>
                        @error('form.descripcion')<div class="field-error">{{ $message }}</div>@enderror
                    </div>

                    <div style="display:flex;flex-direction:column;gap:8px;border-top:1px solid var(--border);padding-top:12px;">
                        <div class="label-xs" style="margin-bottom:4px;">{{ __('configurador.resultados.banderas') }}</div>
                        <label style="display:flex;align-items:center;gap:8px;">
                            <input type="checkbox" wire:model="form.es_contacto_efectivo"/>
                            <span style="font-size:13px;color:var(--text-secondary);">{{ __('configurador.resultados.es_contacto_efectivo') }}</span>
                        </label>
                        <label style="display:flex;align-items:center;gap:8px;">
                            <input type="checkbox" wire:model="form.requiere_compromiso"/>
                            <span style="font-size:13px;color:var(--text-secondary);">{{ __('configurador.resultados.requiere_compromiso') }}</span>
                        </label>
                        <label style="display:flex;align-items:center;gap:8px;">
                            <input type="checkbox" wire:model="form.requiere_causa"/>
                            <span style="font-size:13px;color:var(--text-secondary);">{{ __('configurador.resultados.requiere_causa') }}</span>
                        </label>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                        <div>
                            <label class="field-label">{{ __('configurador.campo_orden') }}</label>
                            <input type="number" min="0" wire:model="form.orden"
                                   class="input @error('form.orden') input-error @enderror"/>
                            @error('form.orden')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label class="field-label">{{ __('configurador.campo_estado') }}</label>
                            <label style="display:flex;align-items:center;gap:8px;padding-top:8px;">
                                <input type="checkbox" wire:model="form.activo"/>
                                <span style="font-size:13px;color:var(--text-secondary);">{{ __('configurador.activo') }}</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="drawer-footer">
                @if($editandoId !== null)
                    <button type="button"
                            wire:click="eliminar({{ $editandoId }})"
                            wire:confirm="{{ __('configurador.resultados.confirm_eliminar') }}"
                            class="btn btn-ghost"
                            style="color:var(--danger-text);margin-right:auto;">
                        {{ __('common.delete') }}
                    </button>
                @endif
                <button type="button" wire:click="cerrarForm" class="btn btn-ghost">{{ __('common.cancel') }}</button>
                <button type="button" wire:click="guardar" class="btn btn-primary">{{ __('common.save') }}</button>
            </div>
        </div>
    @endif
</div>
