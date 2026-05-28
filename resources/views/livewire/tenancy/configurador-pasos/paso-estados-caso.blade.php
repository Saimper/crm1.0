<div>
    @if(session('paso-estados-caso-ok'))
        <div class="alert alert-success" style="margin-bottom:14px;">{{ session('paso-estados-caso-ok') }}</div>
    @endif
    @if(session('paso-estados-caso-error'))
        <div class="alert alert-warning" style="margin-bottom:14px;">{{ session('paso-estados-caso-error') }}</div>
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
            <span style="font-size:12px;color:var(--text-tertiary);">{{ __('configurador.estados_caso.n_estados', ['n' => $estados->count()]) }}</span>
            <button type="button" wire:click="abrirFormCrear" class="btn btn-primary">
                <x-ui.icon name="plus" :size="14" />
                <span>{{ __('configurador.estados_caso.nuevo') }}</span>
            </button>
        </div>

        @if($estados->isEmpty())
            <div class="empty">
                <div class="empty-icon"><x-ui.icon name="folder" :size="32" /></div>
                <div class="empty-title">{{ __('configurador.estados_caso.sin_titulo') }}</div>
                <div class="empty-desc">{{ __('configurador.estados_caso.sin_desc') }}</div>
            </div>
        @else
            <table class="table table-compact table-clickable">
                <thead>
                    <tr>
                        <th style="width:160px;">{{ __('configurador.campo_codigo') }}</th>
                        <th>{{ __('common.name') }}</th>
                        <th>{{ __('configurador.campo_descripcion') }}</th>
                        <th style="width:90px;">{{ __('configurador.estados_caso.col_terminal') }}</th>
                        <th class="num" style="width:70px;">{{ __('configurador.campo_orden') }}</th>
                        <th style="width:110px;">{{ __('configurador.campo_estado') }}</th>
                        <th style="width:60px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($estados as $e)
                        <tr wire:key="paso-estado-{{ $e->id }}" wire:click="abrirFormEditar({{ $e->id }})">
                            <td><span class="font-mono" style="font-size:12px;">{{ $e->codigo }}</span></td>
                            <td><span style="font-weight:500;">{{ $e->nombre }}</span></td>
                            <td><span style="font-size:12px;color:var(--text-secondary);">{{ $e->descripcion ?? '—' }}</span></td>
                            <td>
                                @if($e->es_terminal)
                                    <span class="badge badge-warning">{{ __('configurador.estados_caso.terminal_badge') }}</span>
                                @else
                                    <span style="font-size:12px;color:var(--text-muted);">—</span>
                                @endif
                            </td>
                            <td class="num">{{ $e->orden }}</td>
                            <td>
                                <span style="display:inline-flex;align-items:center;gap:6px;">
                                    <span class="dot dot-{{ $e->activo ? 'success' : 'neutral' }}"></span>
                                    {{ $e->activo ? __('configurador.activo') : __('configurador.inactivo') }}
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
        <div class="scrim" wire:click="cerrarForm" wire:key="paso-estado-scrim"></div>
        <div class="drawer" wire:key="paso-estado-drawer">
            <div class="drawer-header">
                <div style="font-size:14px;font-weight:600;">
                    {{ $editandoId === null ? __('configurador.estados_caso.drawer_nuevo') : __('configurador.estados_caso.drawer_editar') }}
                </div>
                <button type="button" wire:click="cerrarForm" class="icon-btn" aria-label="{{ __('configurador.cerrar') }}">
                    <x-ui.icon name="x" :size="14" />
                </button>
            </div>
            <div class="drawer-body">
                <div style="display:grid;grid-template-columns:1fr;gap:14px;">
                    <div>
                        <label class="field-label">{{ __('configurador.campo_codigo') }}</label>
                        <input type="text" wire:model="form.codigo" placeholder="ABIERTO" maxlength="50"
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
                    <div>
                        <label style="display:flex;align-items:center;gap:8px;">
                            <input type="checkbox" wire:model="form.es_terminal"/>
                            <span style="font-size:13px;color:var(--text-secondary);">{{ __('configurador.estados_caso.es_terminal') }}</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="drawer-footer">
                @if($editandoId !== null)
                    <button type="button"
                            wire:click="eliminar({{ $editandoId }})"
                            wire:confirm="{{ __('configurador.estados_caso.confirm_eliminar') }}"
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
