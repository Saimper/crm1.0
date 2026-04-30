<div class="space-y-4">
    @if(session('gestion-usuarios-ok'))
        <div class="alert alert-success">{{ session('gestion-usuarios-ok') }}</div>
    @endif
    @if(session('gestion-usuarios-error'))
        <div class="alert alert-danger">{{ session('gestion-usuarios-error') }}</div>
    @endif

    <div class="flex items-center justify-between">
        <div style="font-size:12px;color:var(--text-tertiary);">
            Usuarios con rol en este proyecto:
            <span class="tnum" style="font-weight:600;color:var(--text);">{{ $asignaciones->count() }}</span>
        </div>
        <button type="button" wire:click="abrirFormAsignar" class="btn btn-primary btn-sm">
            <x-ui.icon name="plus" :size="13" />
            <span>Asignar usuario</span>
        </button>
    </div>

    <div class="space-y-3">
        @if($asignaciones->isEmpty())
            <div class="card">
                <div class="empty">
                    <div class="empty-icon"><x-ui.icon name="users" :size="32" /></div>
                    <div class="empty-title">Sin usuarios asignados</div>
                    <div class="empty-desc">Este proyecto aún no tiene usuarios (aparte de ADMIN_GLOBAL).</div>
                </div>
            </div>
        @else
            @foreach($asignaciones as $usuarioId => $rolesUsuario)
                @php $primero = $rolesUsuario->first(); @endphp
                <div class="card card-pad">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <div style="font-weight:600;color:var(--text);">{{ $primero->name }}</div>
                                @if(! $primero->usuario_activo)
                                    <span class="badge badge-neutral">inactivo</span>
                                @endif
                                @if((int) $usuarioId === $usuarioActualId)
                                    <span class="badge badge-primary">tú</span>
                                @endif
                            </div>
                            <div class="code-mono" style="margin-top:2px;">{{ $primero->email }}</div>
                            <div class="mt-2 space-y-1">
                                @foreach($rolesUsuario as $a)
                                    @php
                                        $esCustom = ($a->tipo_rol ?? 'base') === 'custom';
                                        $rolBadge = $esCustom ? 'badge-info' : match ($a->rol_codigo) {
                                            'SUPERVISOR' => 'badge-primary',
                                            'GESTOR'     => 'badge-success',
                                            'AUDITOR'    => 'badge-warning',
                                            default      => 'badge-neutral',
                                        };
                                        $claveRestr = $a->usuario_id.'-'.$a->rol_id;
                                        $carterasRol = $esCustom ? collect() : $restricciones->get($claveRestr, collect());
                                        $accionQuitar = $esCustom
                                            ? "quitarCustom({$a->usuario_id}, {$a->rol_id})"
                                            : "quitar({$a->usuario_id}, {$a->rol_id})";
                                    @endphp
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="badge {{ $rolBadge }}" style="font-weight:600;gap:6px;">
                                            @if($esCustom)<span style="font-size:9px;opacity:0.8;">CUSTOM</span>@endif
                                            {{ $a->rol_codigo }}
                                            <button type="button"
                                                    wire:click="{{ $accionQuitar }}"
                                                    wire:confirm="¿Quitar el rol {{ $a->rol_codigo }} a {{ $primero->name }}?"
                                                    style="background:transparent;border:0;cursor:pointer;color:inherit;line-height:1;font-size:14px;padding:0;"
                                                    title="Quitar rol">×</button>
                                        </span>
                                        @if($esCustom)
                                            <span class="label-xs">todo el proyecto (custom)</span>
                                        @elseif($carterasRol->isEmpty())
                                            <span class="label-xs">todo el proyecto</span>
                                        @else
                                            <span class="label-xs">carteras:</span>
                                            @foreach($carterasRol as $cr)
                                                <span class="badge badge-neutral code-mono" style="font-size:10px;">{{ $cr->cartera_nombre }}</span>
                                            @endforeach
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        @endif
    </div>

    @if($formAsignarVisible)
        <div class="scrim" wire:key="form-asignar-scrim"></div>
        <div class="modal-card" wire:key="form-asignar" style="width:480px;">
            <div class="card-header">
                <div class="card-title">Asignar rol</div>
                <button type="button" wire:click="cerrarFormAsignar" class="icon-btn" aria-label="Cerrar">
                    <x-ui.icon name="x" :size="14" />
                </button>
            </div>
            <div style="padding:20px;">
                <div class="field">
                    <label class="field-label">Correo del usuario</label>
                    <div class="flex items-center gap-2">
                        <input type="email" wire:model="buscarEmail"
                               class="input @error('buscarEmail') input-error @enderror"
                               placeholder="usuario@dominio.com"/>
                        <button type="button" wire:click="buscarUsuario" class="btn btn-secondary" style="white-space:nowrap;">
                            Buscar
                        </button>
                    </div>
                    @error('buscarEmail')<div class="field-error">{{ $message }}</div>@enderror
                </div>

                @if($usuarioBuscadoId !== null)
                    <div class="alert alert-success" style="margin-bottom:14px;">
                        Usuario encontrado: <span style="font-weight:600;">{{ $usuarioBuscadoNombre }}</span>
                    </div>

                    <div class="field">
                        <label class="field-label">Rol</label>
                        <select wire:model.live="rolAsignarValor" class="select @error('rolAsignarValor') input-error @enderror">
                            <option value="">—</option>
                            <optgroup label="Roles base">
                                @foreach($rolesAsignablesBase as $r)
                                    <option value="base:{{ $r->id }}">{{ $r->codigo }} — {{ $r->nombre }}</option>
                                @endforeach
                            </optgroup>
                            @if($rolesAsignablesCustom->isNotEmpty())
                                <optgroup label="Roles custom del proyecto">
                                    @foreach($rolesAsignablesCustom as $rc)
                                        <option value="custom:{{ $rc->id }}">{{ $rc->codigo }} — {{ $rc->nombre }}</option>
                                    @endforeach
                                </optgroup>
                            @endif
                        </select>
                        @error('rolAsignarValor')<div class="field-error">{{ $message }}</div>@enderror
                    </div>

                    @php $esRolCustom = str_starts_with($rolAsignarValor, 'custom:'); @endphp

                    @if($esRolCustom)
                        <div class="alert alert-info" style="font-size:12px;">
                            Los roles custom aplican a todo el proyecto. La restricción por cartera no aplica en esta versión.
                        </div>
                    @endif

                    @if(! $esRolCustom && $carterasDelProyecto->isNotEmpty())
                        <div class="field">
                            <label class="field-label">Restringir a carteras (opcional)</label>
                            <div class="field-help" style="margin-top:0;margin-bottom:6px;">
                                Si no seleccionas ninguna, el rol aplica a todo el proyecto.
                            </div>
                            <div style="max-height:160px;overflow-y:auto;border:1px solid var(--border);border-radius:6px;padding:8px;">
                                @foreach($carterasDelProyecto as $c)
                                    <label class="flex items-center gap-2" style="font-size:12px;padding:3px 0;">
                                        <input type="checkbox" value="{{ $c->id }}" wire:model="carterasSeleccionadas" class="checkbox"/>
                                        <span class="font-mono" style="color:var(--text-tertiary);">{{ $c->codigo }}</span>
                                        <span>{{ $c->nombre }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endif
            </div>
            <div class="drawer-footer">
                <button type="button" wire:click="cerrarFormAsignar" class="btn btn-secondary">Cancelar</button>
                @if($usuarioBuscadoId !== null)
                    <button type="button" wire:click="asignar" class="btn btn-primary">Asignar</button>
                @endif
            </div>
        </div>
    @endif
</div>
