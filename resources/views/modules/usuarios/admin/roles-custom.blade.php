<div class="space-y-4">
    @if(session('roles-custom-ok'))
        <div class="alert alert-success">{{ session('roles-custom-ok') }}</div>
    @endif
    @if(session('roles-custom-error'))
        <div class="alert alert-danger">{{ session('roles-custom-error') }}</div>
    @endif
    @error('form')<div class="alert alert-danger">{{ $message }}</div>@enderror

    <div class="flex items-center justify-between">
        <div style="font-size:12px;color:var(--text-tertiary);">
            {{ __('usuarios.roles_summary', ['count' => $rolesCustom->count()]) }}
        </div>
        <button type="button" wire:click="abrirFormCrear" class="btn btn-primary btn-sm">
            <x-ui.icon name="plus" :size="13" />
            <span>{{ __('usuarios.btn_new_custom_role') }}</span>
        </button>
    </div>

    <div class="space-y-3">
        <div class="card card-pad">
            <div style="font-size:12px;color:var(--text-tertiary);font-weight:600;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:10px;">
                {{ __('usuarios.section_base_roles') }}
            </div>
            <div class="space-y-2">
                @foreach($rolesBase as $rb)
                    <div class="flex items-center justify-between" style="padding:6px 0;">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="badge badge-neutral">{{ __('usuarios.badge_system') }}</span>
                                <span style="font-weight:600;font-family:var(--font-mono);">{{ $rb->codigo }}</span>
                                <span style="color:var(--text-secondary);">{{ $rb->nombre }}</span>
                            </div>
                            @if($rb->descripcion)
                                <div style="font-size:12px;color:var(--text-tertiary);margin-top:2px;">{{ $rb->descripcion }}</div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        @if($rolesCustom->isEmpty())
            <div class="card">
                <div class="empty">
                    <div class="empty-icon"><x-ui.icon name="shield" :size="32" /></div>
                    <div class="empty-title">{{ __('usuarios.empty_custom_roles_title') }}</div>
                    <div class="empty-desc">{{ __('usuarios.empty_custom_roles_desc') }}</div>
                </div>
            </div>
        @else
            @foreach($rolesCustom as $rc)
                <div class="card card-pad">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="badge badge-primary">{{ __('usuarios.badge_custom_role') }}</span>
                                <span style="font-weight:600;font-family:var(--font-mono);">{{ $rc->codigo }}</span>
                                <span style="color:var(--text-secondary);">{{ $rc->nombre }}</span>
                                @if(! $rc->activo)
                                    <span class="badge badge-neutral">{{ __('usuarios.badge_inactive_role') }}</span>
                                @endif
                            </div>
                            @if($rc->descripcion)
                                <div style="font-size:12px;color:var(--text-tertiary);margin-top:2px;">{{ $rc->descripcion }}</div>
                            @endif
                            <div style="font-size:11px;color:var(--text-tertiary);margin-top:6px;">
                                {{ __('usuarios.permissions_count', ['count' => $conteoPermisos[$rc->id] ?? 0]) }} ·
                                {{ __('usuarios.assignments_count', ['count' => $conteoAsignaciones[$rc->id] ?? 0]) }}
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" wire:click="abrirFormEditar({{ $rc->id }})" class="btn btn-secondary btn-sm">
                                {{ __('common.edit') }}
                            </button>
                            <button type="button"
                                    wire:click="eliminar({{ $rc->id }})"
                                    wire:confirm="{{ __('usuarios.confirm_delete_role', ['code' => $rc->codigo]) }}"
                                    class="btn btn-danger btn-sm">
                                {{ __('common.delete') }}
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        @endif
    </div>

    @if($formVisible)
        <div class="scrim" wire:key="form-rol-custom-scrim"></div>
        <div class="modal-card" wire:key="form-rol-custom" style="width:640px;max-height:90vh;overflow-y:auto;">
            <div class="card-header">
                <div class="card-title">
                    {{ $editandoId === null ? __('usuarios.modal_new_custom_role') : __('usuarios.modal_edit_custom_role') }}
                </div>
                <button type="button" wire:click="cerrarForm" class="icon-btn" aria-label="{{ __('usuarios.aria_close') }}">
                    <x-ui.icon name="x" :size="14" />
                </button>
            </div>
            <div style="padding:20px;">
                <div class="field">
                    <label class="field-label">{{ __('usuarios.label_role_code') }}</label>
                    <input type="text" wire:model="form_codigo"
                           class="input @error('form_codigo') input-error @enderror"
                           placeholder="GESTOR_TELEVENTAS"
                           {{ $editandoId !== null ? 'disabled' : '' }} />
                    <div class="field-help">{{ __('usuarios.help_role_code') }}</div>
                </div>

                <div class="field">
                    <label class="field-label">{{ __('usuarios.label_role_name') }}</label>
                    <input type="text" wire:model="form_nombre"
                           class="input @error('form_nombre') input-error @enderror"
                           placeholder="Gestor de televentas" />
                </div>

                <div class="field">
                    <label class="field-label">{{ __('usuarios.label_role_description') }}</label>
                    <textarea wire:model="form_descripcion"
                              class="input @error('form_descripcion') input-error @enderror"
                              rows="2" placeholder="{{ __('usuarios.placeholder_optional') }}"></textarea>
                </div>

                <div class="field">
                    <label class="field-label">{{ __('usuarios.label_permissions') }}</label>
                    <div class="field-help">
                        {!! __('usuarios.help_permissions') !!}
                    </div>
                    <div style="max-height:380px;overflow-y:auto;border:1px solid var(--border);border-radius:6px;padding:10px;">
                        @foreach($permisosDisponibles as $grupo => $permisos)
                            <div style="margin-bottom:14px;">
                                <div style="font-size:11px;color:var(--text-tertiary);font-weight:700;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:6px;">
                                    {{ $grupo }}
                                </div>
                                @foreach($permisos as $p)
                                    <label class="flex items-center gap-2" style="font-size:12px;padding:3px 0;">
                                        <input type="checkbox" value="{{ $p->codigo }}"
                                               wire:model="form_permisos" class="checkbox" />
                                        <span class="font-mono" style="color:var(--text-tertiary);">{{ $p->codigo }}</span>
                                        <span>{{ $p->nombre }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="drawer-footer">
                <button type="button" wire:click="cerrarForm" class="btn btn-secondary">{{ __('common.cancel') }}</button>
                <button type="button" wire:click="guardar" class="btn btn-primary">
                    {{ $editandoId === null ? __('usuarios.btn_create') : __('usuarios.btn_save_changes') }}
                </button>
            </div>
        </div>
    @endif
</div>
