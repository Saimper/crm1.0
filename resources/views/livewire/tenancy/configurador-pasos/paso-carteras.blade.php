<div>
    @if(session('paso-carteras-ok'))
        <div class="alert alert-success" style="margin-bottom:14px;">{{ session('paso-carteras-ok') }}</div>
    @endif

    @if(session('paso-carteras-error'))
        <div class="alert alert-warning" style="margin-bottom:14px;">{{ session('paso-carteras-error') }}</div>
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
            <span style="font-size:12px;color:var(--text-tertiary);">{{ $carteras->count() }} carteras</span>
            <button type="button" wire:click="abrirFormCrear" class="btn btn-primary">
                <x-ui.icon name="plus" :size="14" />
                <span>Nueva cartera</span>
            </button>
        </div>

        @if($carteras->isEmpty())
            <div class="empty">
                <div class="empty-icon"><x-ui.icon name="folder" :size="32" /></div>
                <div class="empty-title">Sin carteras</div>
                <div class="empty-desc">Crea la primera cartera para clasificar los casos del proyecto.</div>
            </div>
        @else
            <table class="table table-compact table-clickable">
                <thead>
                    <tr>
                        <th style="width:160px;">Código</th>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th class="num" style="width:80px;">Casos</th>
                        <th style="width:120px;">Estado</th>
                        <th style="width:60px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($carteras as $c)
                        <tr wire:key="paso-cartera-{{ $c->id }}" wire:click="abrirFormEditar({{ $c->id }})">
                            <td><span class="font-mono" style="font-size:12px;">{{ $c->codigo }}</span></td>
                            <td><span style="font-weight:500;">{{ $c->nombre }}</span></td>
                            <td><span style="font-size:12px;color:var(--text-secondary);">{{ $c->descripcion ?? '—' }}</span></td>
                            <td class="num">{{ $c->total_casos }}</td>
                            <td>
                                <span style="display:inline-flex;align-items:center;gap:6px;">
                                    <span class="dot dot-{{ $c->activo ? 'success' : 'neutral' }}"></span>
                                    {{ $c->activo ? 'Activa' : 'Inactiva' }}
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
        <div class="scrim" wire:click="cerrarForm" wire:key="paso-cartera-scrim"></div>
        <div class="drawer" wire:key="paso-cartera-drawer">
            <div class="drawer-header">
                <div style="font-size:14px;font-weight:600;">
                    {{ $editandoId === null ? 'Nueva cartera' : 'Editar cartera' }}
                </div>
                <button type="button" wire:click="cerrarForm" class="icon-btn" aria-label="Cerrar">
                    <x-ui.icon name="x" :size="14" />
                </button>
            </div>
            <div class="drawer-body">
                <div style="display:grid;grid-template-columns:1fr;gap:14px;">
                    <div>
                        <label class="field-label">Código</label>
                        <input type="text" wire:model="form.codigo" placeholder="CARTERA_PRINCIPAL"
                               class="input mono uppercase @error('form.codigo') input-error @enderror" maxlength="80"/>
                        @error('form.codigo')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="field-label">Nombre</label>
                        <input type="text" wire:model="form.nombre" maxlength="200"
                               class="input @error('form.nombre') input-error @enderror"/>
                        @error('form.nombre')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="field-label">Descripción (opcional)</label>
                        <textarea wire:model="form.descripcion" rows="3" maxlength="500"
                                  class="input @error('form.descripcion') input-error @enderror"></textarea>
                        @error('form.descripcion')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label style="display:flex;align-items:center;gap:8px;">
                            <input type="checkbox" wire:model="form.activo"/>
                            <span style="font-size:13px;color:var(--text-secondary);">Cartera activa</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="drawer-footer">
                @if($editandoId !== null)
                    <button type="button"
                            wire:click="eliminarCartera({{ $editandoId }})"
                            wire:confirm="¿Eliminar esta cartera? No se puede deshacer si no tiene casos asociados."
                            class="btn btn-ghost"
                            style="color:var(--danger-text);margin-right:auto;">
                        Eliminar
                    </button>
                @endif
                <button type="button" wire:click="cerrarForm" class="btn btn-ghost">Cancelar</button>
                <button type="button" wire:click="guardarCartera" class="btn btn-primary">Guardar</button>
            </div>
        </div>
    @endif
</div>
