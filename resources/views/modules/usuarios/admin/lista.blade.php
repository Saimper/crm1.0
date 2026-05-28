<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">{{ __('usuarios.page_users_title') }}</h1>
            <div class="page-subtitle">{{ __('usuarios.page_users_subtitle') }}</div>
        </div>
        <div style="display:flex;gap:8px;">
            <a href="{{ route('admin.dashboard') }}" wire:navigate class="btn btn-ghost btn-sm">{{ __('usuarios.back_to_panel') }}</a>
            <button type="button" wire:click="abrirFormCrearUsuario" class="btn btn-primary">
                <x-ui.icon name="plus" :size="14" />
                {{ __('usuarios.btn_new_user') }}
            </button>
        </div>
    </div>

    @if(session('admin-usuarios-ok'))
        <div class="alert alert-success" style="margin-bottom:14px;">{{ session('admin-usuarios-ok') }}</div>
    @endif
    @if(session('admin-usuarios-error'))
        <div class="alert alert-danger" style="margin-bottom:14px;">{{ session('admin-usuarios-error') }}</div>
    @endif

    {{-- Listado --}}
    <div class="card" style="padding:0;margin-bottom:14px;">
        <div class="card-header">
            <span class="card-title">{{ __('usuarios.list_title') }}</span>
            <span style="font-size:12px;color:var(--text-tertiary);">{{ __('usuarios.list_count', ['count' => $usuarios->count()]) }}</span>
        </div>
        <div style="padding:12px 16px;border-bottom:1px solid var(--border);display:flex;gap:10px;align-items:center;">
            <div style="position:relative;width:280px;">
                <span style="position:absolute;left:9px;top:11px;color:var(--text-muted);pointer-events:none;">
                    <x-ui.icon name="search" :size="13" />
                </span>
                <input type="text" wire:model.live.debounce.300ms="busqueda"
                       class="input" placeholder="{{ __('usuarios.search_placeholder') }}" style="padding-left:28px;"/>
            </div>
        </div>
        @if($usuarios->isEmpty())
            <div class="empty">
                <div class="empty-icon"><x-ui.icon name="users" :size="32" /></div>
                <div class="empty-title">{{ __('usuarios.empty_users_title') }}</div>
                <div class="empty-desc">{{ __('usuarios.empty_users_desc') }}</div>
            </div>
        @else
            <table class="table table-compact">
                <thead>
                    <tr>
                        <th>{{ __('usuarios.col_name') }}</th>
                        <th style="width:240px;">{{ __('usuarios.col_email') }}</th>
                        <th style="width:160px;">{{ __('usuarios.col_global_role') }}</th>
                        <th class="num" style="width:100px;">{{ __('usuarios.col_assignments') }}</th>
                        <th style="width:110px;">{{ __('usuarios.col_status') }}</th>
                        <th style="width:120px;text-align:right;">{{ __('usuarios.col_actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($usuarios as $u)
                        <tr wire:key="usuario-{{ $u->id }}">
                            <td>
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <div class="avatar" style="background:var(--bg-subtle);color:var(--text-secondary);border-color:var(--border);">
                                        {{ \Illuminate\Support\Str::of($u->name)->explode(' ')->map(fn($p) => mb_substr($p, 0, 1))->take(2)->implode('') }}
                                    </div>
                                    <span style="font-weight:500;">{{ $u->name }}</span>
                                </div>
                            </td>
                            <td><span class="font-mono" style="font-size:12px;">{{ $u->email }}</span></td>
                            <td>
                                @if($u->es_admin_global)
                                    <span class="badge badge-danger">ADMIN_GLOBAL</span>
                                @else
                                    <span style="color:var(--text-tertiary);font-size:12px;">—</span>
                                @endif
                            </td>
                            <td class="num">{{ isset($asignaciones[$u->id]) ? $asignaciones[$u->id]->count() : 0 }}</td>
                            <td>
                                <span style="display:inline-flex;align-items:center;gap:6px;">
                                    <span class="dot dot-{{ $u->activo ? 'success' : 'neutral' }}"></span>
                                    {{ $u->activo ? __('usuarios.status_active') : __('usuarios.status_inactive') }}
                                </span>
                            </td>
                            <td style="text-align:right;">
                                <button type="button" wire:click="abrirFormAsignacion({{ $u->id }})"
                                        class="icon-btn" title="{{ __('usuarios.title_assign_role') }}">
                                    <x-ui.icon name="plus" :size="12" />
                                </button>
                                <button type="button" wire:click="abrirFormEditarUsuario({{ $u->id }})"
                                        class="icon-btn" title="{{ __('usuarios.title_edit') }}">
                                    <x-ui.icon name="edit" :size="12" />
                                </button>
                                @if($u->es_admin_global)
                                    <button type="button" wire:click="revocarAdminGlobal({{ $u->id }})"
                                            wire:confirm="{{ __('usuarios.confirm_revoke_admin') }}"
                                            class="icon-btn" style="color:var(--danger-text);" title="{{ __('usuarios.title_revoke_admin') }}">
                                        <x-ui.icon name="shield" :size="12" />
                                    </button>
                                @else
                                    <button type="button" wire:click="promoverAdminGlobal({{ $u->id }})"
                                            wire:confirm="{{ __('usuarios.confirm_promote_admin') }}"
                                            class="icon-btn" style="color:var(--warning-text);" title="{{ __('usuarios.title_promote_admin') }}">
                                        <x-ui.icon name="shield" :size="12" />
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- Matriz de acceso --}}
    @if($proyectos->isNotEmpty() && $usuarios->isNotEmpty())
        <div class="card" style="padding:0;">
            <div class="card-header">
                <span class="card-title">{{ __('usuarios.matrix_title') }}</span>
            </div>
            <div style="overflow-x:auto;">
                <table class="table table-compact">
                    <thead>
                        <tr>
                            <th>{{ __('usuarios.col_user') }}</th>
                            @foreach($proyectos as $p)
                                <th style="width:140px;"><span class="font-mono" style="font-size:11px;">{{ $p->codigo }}</span></th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($usuarios as $u)
                            <tr wire:key="matriz-{{ $u->id }}">
                                <td style="font-weight:500;">{{ $u->name }}</td>
                                @foreach($proyectos as $p)
                                    @php
                                        $rolesDelUsuarioEnProyecto = isset($asignaciones[$u->id])
                                            ? $asignaciones[$u->id]->where('proyecto_id', $p->id)
                                            : collect();
                                    @endphp
                                    <td>
                                        @if($u->es_admin_global)
                                            <span class="badge badge-danger">Admin</span>
                                        @elseif($rolesDelUsuarioEnProyecto->isEmpty())
                                            <span style="color:var(--text-muted);">—</span>
                                        @else
                                            @foreach($rolesDelUsuarioEnProyecto as $a)
                                                @php
                                                    $rolBadge = match ($a->rol_codigo) {
                                                        'SUPERVISOR' => 'badge-warning',
                                                        'GESTOR'     => 'badge-primary',
                                                        'AUDITOR'    => 'badge-info',
                                                        default      => 'badge-neutral',
                                                    };
                                                @endphp
                                                <span class="badge {{ $rolBadge }}">{{ $a->rol_codigo }}</span>
                                            @endforeach
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Drawer: usuario --}}
    @if($formUsuarioVisible)
        <div class="scrim" wire:click="cerrarFormUsuario" wire:key="form-usuario-scrim"></div>
        <div class="drawer" wire:key="form-usuario">
            <div class="drawer-header">
                <div style="font-size:14px;font-weight:600;">
                    {{ $editandoUsuarioId === null ? __('usuarios.drawer_new_user') : __('usuarios.drawer_edit_user') }}
                </div>
                <button type="button" wire:click="cerrarFormUsuario" class="icon-btn" aria-label="{{ __('usuarios.aria_close') }}">
                    <x-ui.icon name="x" :size="14" />
                </button>
            </div>
            <div class="drawer-body">
                <div class="field">
                    <label class="field-label">{{ __('usuarios.label_user_name') }}</label>
                    <input type="text" wire:model="formUsuario.name"
                           class="input @error('formUsuario.name') input-error @enderror"/>
                    @error('formUsuario.name')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="field">
                    <label class="field-label">{{ __('usuarios.label_user_email') }}</label>
                    <input type="email" wire:model="formUsuario.email"
                           class="input mono @error('formUsuario.email') input-error @enderror"/>
                    @error('formUsuario.email')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="field">
                    <label class="field-label">
                        {{ $editandoUsuarioId !== null ? __('usuarios.label_password_keep_empty') : __('usuarios.label_password') }}
                    </label>
                    <input type="password" wire:model="formUsuario.password"
                           class="input @error('formUsuario.password') input-error @enderror"/>
                    @error('formUsuario.password')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <label style="display:inline-flex;align-items:center;gap:8px;font-size:13px;color:var(--text);">
                    <input type="checkbox" wire:model="formUsuario.activo" class="checkbox"/>
                    <span>{{ __('usuarios.label_active') }}</span>
                </label>

                @if($editandoUsuarioId !== null && isset($asignaciones[$editandoUsuarioId]))
                    <div style="margin-top:20px;">
                        <div class="label-xs">{{ __('usuarios.label_project_assignments') }}</div>
                        <div style="display:flex;flex-direction:column;gap:6px;margin-top:8px;">
                            @foreach($asignaciones[$editandoUsuarioId] as $a)
                                <div style="display:flex;align-items:center;gap:8px;padding:8px 10px;border:1px solid var(--border);border-radius:6px;">
                                    <span class="font-mono" style="font-size:11px;color:var(--text-tertiary);">{{ $a->proyecto_codigo }}</span>
                                    <span style="flex:1;font-size:12px;">{{ $a->proyecto_nombre }}</span>
                                    <span class="badge badge-primary">{{ $a->rol_codigo }}</span>
                                    <button type="button"
                                            wire:click="quitarAsignacion({{ $a->usuario_id }}, {{ $a->proyecto_id }}, {{ $a->rol_id }})"
                                            wire:confirm="{{ __('usuarios.confirm_remove_assignment') }}"
                                            class="icon-btn" style="color:var(--danger-text);" title="{{ __('usuarios.btn_remove') }}">
                                        <x-ui.icon name="x" :size="12" />
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
            <div class="drawer-footer">
                <button type="button" wire:click="cerrarFormUsuario" class="btn btn-ghost">{{ __('common.cancel') }}</button>
                <button type="button" wire:click="guardarUsuario" class="btn btn-primary">{{ __('common.save') }}</button>
            </div>
        </div>
    @endif

    {{-- Drawer: asignación rol --}}
    @if($formAsignacionVisible)
        <div class="scrim" wire:click="cerrarFormAsignacion" wire:key="form-asignacion-scrim"></div>
        <div class="drawer" wire:key="form-asignacion">
            <div class="drawer-header">
                <div style="font-size:14px;font-weight:600;">{{ __('usuarios.drawer_assign_role_title') }}</div>
                <button type="button" wire:click="cerrarFormAsignacion" class="icon-btn" aria-label="{{ __('usuarios.aria_close') }}">
                    <x-ui.icon name="x" :size="14" />
                </button>
            </div>
            <div class="drawer-body">
                <div class="field">
                    <label class="field-label">{{ __('usuarios.label_project') }}</label>
                    <select wire:model="asignarProyectoId" class="select @error('asignarProyectoId') input-error @enderror">
                        <option value="">—</option>
                        @foreach($proyectos as $p)
                            <option value="{{ $p->id }}">{{ $p->codigo }} — {{ $p->nombre }}</option>
                        @endforeach
                    </select>
                    @error('asignarProyectoId')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="field">
                    <label class="field-label">{{ __('usuarios.label_role') }}</label>
                    <select wire:model="asignarRolId" class="select @error('asignarRolId') input-error @enderror">
                        <option value="">—</option>
                        @foreach($roles as $r)
                            <option value="{{ $r->id }}">{{ $r->codigo }} — {{ $r->nombre }}</option>
                        @endforeach
                    </select>
                    @error('asignarRolId')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="drawer-footer">
                <button type="button" wire:click="cerrarFormAsignacion" class="btn btn-ghost">{{ __('common.cancel') }}</button>
                <button type="button" wire:click="guardarAsignacion" class="btn btn-primary">{{ __('usuarios.btn_assign') }}</button>
            </div>
        </div>
    @endif
</div>
