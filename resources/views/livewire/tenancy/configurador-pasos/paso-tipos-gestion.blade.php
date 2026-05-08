<div>
    @if(session('paso-tipos-gestion-ok'))
        <div class="alert alert-success" style="margin-bottom:14px;">{{ session('paso-tipos-gestion-ok') }}</div>
    @endif
    @if(session('paso-tipos-gestion-error'))
        <div class="alert alert-warning" style="margin-bottom:14px;">{{ session('paso-tipos-gestion-error') }}</div>
    @endif

    <div class="card" style="padding:0;">
        <div style="padding:12px 16px;border-bottom:1px solid var(--border);display:flex;gap:10px;align-items:center;">
            <div style="position:relative;width:280px;">
                <span style="position:absolute;left:9px;top:11px;color:var(--text-muted);pointer-events:none;">
                    <x-ui.icon name="search" :size="13" />
                </span>
                <input type="text" wire:model.live.debounce.300ms="busqueda"
                       class="input" placeholder="Buscar…" style="padding-left:28px;"/>
            </div>
            <span style="flex:1;"></span>
            <span style="font-size:12px;color:var(--text-tertiary);">{{ $tipos->count() }} tipos</span>
            <button type="button" wire:click="abrirFormCrear" class="btn btn-primary">
                <x-ui.icon name="plus" :size="14" />
                <span>Nuevo tipo</span>
            </button>
        </div>

        @if($tipos->isEmpty())
            <div class="empty">
                <div class="empty-icon"><x-ui.icon name="folder" :size="32" /></div>
                <div class="empty-title">Sin tipos de gestión</div>
                <div class="empty-desc">Define los tipos de gestión (ej. Llamada, Visita, Email).</div>
            </div>
        @else
            <table class="table table-compact table-clickable">
                <thead>
                    <tr>
                        <th style="width:160px;">Código</th>
                        <th>Nombre</th>
                        <th class="num" style="width:70px;">Orden</th>
                        <th style="width:110px;">Estado</th>
                        <th style="width:60px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tipos as $t)
                        <tr wire:key="paso-tipo-{{ $t->id }}" wire:click="abrirFormEditar({{ $t->id }})">
                            <td><span class="font-mono" style="font-size:12px;">{{ $t->codigo }}</span></td>
                            <td><span style="font-weight:500;">{{ $t->nombre }}</span></td>
                            <td class="num">{{ $t->orden }}</td>
                            <td>
                                <span style="display:inline-flex;align-items:center;gap:6px;">
                                    <span class="dot dot-{{ $t->activo ? 'success' : 'neutral' }}"></span>
                                    {{ $t->activo ? 'Activo' : 'Inactivo' }}
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
        <div class="scrim" wire:click="cerrarForm" wire:key="paso-tipo-scrim"></div>
        <div class="drawer" wire:key="paso-tipo-drawer">
            <div class="drawer-header">
                <div style="font-size:14px;font-weight:600;">
                    {{ $editandoId === null ? 'Nuevo tipo de gestión' : 'Editar tipo de gestión' }}
                </div>
                <button type="button" wire:click="cerrarForm" class="icon-btn" aria-label="Cerrar">
                    <x-ui.icon name="x" :size="14" />
                </button>
            </div>
            <div class="drawer-body">
                <div style="display:grid;grid-template-columns:1fr;gap:14px;">
                    <div>
                        <label class="field-label">Código</label>
                        <input type="text" wire:model="form.codigo" placeholder="LLAMADA" maxlength="50"
                               class="input mono uppercase @error('form.codigo') input-error @enderror"/>
                        @error('form.codigo')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="field-label">Nombre</label>
                        <input type="text" wire:model="form.nombre" maxlength="150"
                               class="input @error('form.nombre') input-error @enderror"/>
                        @error('form.nombre')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                        <div>
                            <label class="field-label">Orden</label>
                            <input type="number" min="0" wire:model="form.orden"
                                   class="input @error('form.orden') input-error @enderror"/>
                            @error('form.orden')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label class="field-label">Estado</label>
                            <label style="display:flex;align-items:center;gap:8px;padding-top:8px;">
                                <input type="checkbox" wire:model="form.activo"/>
                                <span style="font-size:13px;color:var(--text-secondary);">Activo</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="drawer-footer">
                @if($editandoId !== null)
                    <button type="button"
                            wire:click="eliminar({{ $editandoId }})"
                            wire:confirm="¿Eliminar este tipo de gestión? Solo se permite si no hay gestiones registradas."
                            class="btn btn-ghost"
                            style="color:var(--danger-text);margin-right:auto;">
                        Eliminar
                    </button>
                @endif
                <button type="button" wire:click="cerrarForm" class="btn btn-ghost">Cancelar</button>
                <button type="button" wire:click="guardar" class="btn btn-primary">Guardar</button>
            </div>
        </div>
    @endif
</div>
