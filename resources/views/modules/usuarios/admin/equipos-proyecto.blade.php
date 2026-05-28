<div class="space-y-4">
    <div class="card card-pad flex items-center justify-between">
        <div>
            <h3 style="font-size:13px;font-weight:600;color:var(--text);">{{ __('usuarios.equipos_title') }}</h3>
            <p style="font-size:12px;color:var(--text-tertiary);margin-top:4px;">
                {{ __('usuarios.equipos_subtitle') }}
            </p>
        </div>
        @if(! $formEquipoVisible)
            <button type="button" wire:click="abrirFormCrear" class="btn btn-primary btn-sm">
                <x-ui.icon name="plus" :size="13" />
                <span>{{ __('usuarios.btn_new_team') }}</span>
            </button>
        @endif
    </div>

    @if($formEquipoVisible)
        <div class="card card-pad" style="background:var(--primary-soft);border-color:var(--primary-soft-border);">
            <h4 style="font-size:13px;font-weight:600;color:var(--primary-text);margin-bottom:12px;">
                {{ $equipoEditandoId === null ? __('usuarios.form_create_team') : __('usuarios.form_edit_team') }}
            </h4>
            <form wire:submit.prevent="guardarEquipo" class="grid grid-cols-1 md:grid-cols-4 gap-3">
                <div class="field">
                    <label class="field-label">{{ __('usuarios.label_code') }}</label>
                    <input type="text" wire:model="formCodigo"
                           class="input font-mono uppercase @error('formCodigo') input-error @enderror"
                           placeholder="{{ __('usuarios.placeholder_code') }}"/>
                    @error('formCodigo')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="field md:col-span-2">
                    <label class="field-label">{{ __('usuarios.label_team_name') }}</label>
                    <input type="text" wire:model="formNombre"
                           class="input @error('formNombre') input-error @enderror"
                           placeholder="{{ __('usuarios.placeholder_team_name') }}"/>
                    @error('formNombre')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="field">
                    <label class="field-label">{{ __('usuarios.label_team_active') }}</label>
                    <select wire:model="formActivo" class="select">
                        <option value="1">{{ __('usuarios.option_yes') }}</option>
                        <option value="0">{{ __('usuarios.option_no') }}</option>
                    </select>
                </div>
                <div class="field md:col-span-4">
                    <label class="field-label">{{ __('usuarios.label_description') }}</label>
                    <textarea wire:model="formDescripcion" rows="2" class="textarea @error('formDescripcion') input-error @enderror"></textarea>
                    @error('formDescripcion')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="md:col-span-4 flex items-center justify-end gap-2">
                    <button type="button" wire:click="cerrarFormEquipo" class="btn btn-secondary">{{ __('common.cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('common.save') }}</button>
                </div>
            </form>
        </div>
    @endif

    <div class="card">
        @if($equipos->isEmpty())
            <div class="empty">
                <div class="empty-icon"><x-ui.icon name="briefcase" :size="32" /></div>
                <div class="empty-title">{{ __('usuarios.empty_teams_title') }}</div>
                <div class="empty-desc">{{ __('usuarios.empty_teams_desc') }}</div>
            </div>
        @else
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('usuarios.col_code') }}</th>
                        <th>{{ __('usuarios.col_team_name') }}</th>
                        <th>{{ __('usuarios.col_description') }}</th>
                        <th class="num">{{ __('usuarios.col_members') }}</th>
                        <th style="text-align:center;">{{ __('usuarios.col_team_status') }}</th>
                        <th style="text-align:right;">{{ __('usuarios.col_team_actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($equipos as $e)
                        <tr>
                            <td class="code-mono">{{ $e->codigo }}</td>
                            <td>{{ $e->nombre }}</td>
                            <td style="color:var(--text-tertiary);font-size:12px;">{{ $e->descripcion }}</td>
                            <td class="num">{{ $e->miembros_count }}</td>
                            <td style="text-align:center;">
                                @if($e->activo)
                                    <span class="badge badge-success">{{ __('usuarios.badge_active') }}</span>
                                @else
                                    <span class="badge badge-neutral">{{ __('usuarios.badge_inactive') }}</span>
                                @endif
                            </td>
                            <td style="text-align:right;">
                                @can('reportes.operativos', app('tenancy.proyecto_activo')->id)
                                    <a href="{{ route('proyectos.reportes.equipos', ['proyecto_id' => app('tenancy.proyecto_activo')->id, 'equipo' => $e->id]) }}"
                                       wire:navigate class="btn btn-ghost btn-sm" style="text-decoration:none;">{{ __('usuarios.btn_view_report') }}</a>
                                @endcan
                                <button type="button" wire:click="gestionarMiembros({{ $e->id }})" class="btn btn-ghost btn-sm">{{ __('usuarios.btn_members') }}</button>
                                <button type="button" wire:click="abrirFormEditar({{ $e->id }})" class="btn btn-ghost btn-sm">{{ __('common.edit') }}</button>
                                @if($e->activo)
                                    <button type="button" wire:click="desactivar({{ $e->id }})"
                                            class="btn btn-ghost btn-sm" style="color:var(--danger-text);">{{ __('usuarios.btn_deactivate') }}</button>
                                @else
                                    <button type="button" wire:click="activar({{ $e->id }})"
                                            class="btn btn-ghost btn-sm" style="color:var(--success-text);">{{ __('usuarios.btn_activate') }}</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @if($gestionandoEquipoId !== null)
        <div class="card card-pad space-y-3" style="border-color:var(--primary-soft-border);">
            <div class="flex items-center justify-between">
                <h4 style="font-size:13px;font-weight:600;color:var(--text);">{{ __('usuarios.members_section_title') }}</h4>
                <button type="button" wire:click="cerrarMiembros" class="btn btn-ghost btn-sm">
                    <x-ui.icon name="x" :size="13" />
                    <span>{{ __('usuarios.btn_close_members') }}</span>
                </button>
            </div>

            <form wire:submit.prevent="buscarUsuario" class="flex items-end gap-2">
                <div class="field flex-1" style="margin-bottom:0;">
                    <label class="field-label">{{ __('usuarios.label_user_email_search') }}</label>
                    <input type="email" wire:model="buscarEmail"
                           class="input @error('buscarEmail') input-error @enderror"
                           placeholder="{{ __('usuarios.placeholder_email') }}"/>
                    @error('buscarEmail')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <button type="submit" class="btn btn-secondary">{{ __('common.search') }}</button>
                @if($usuarioBuscadoId !== null)
                    <button type="button" wire:click="agregarMiembro" class="btn btn-primary">
                        {{ __('usuarios.btn_add_member', ['name' => $usuarioBuscadoNombre]) }}
                    </button>
                @endif
            </form>

            @if($miembros->isEmpty())
                <div class="empty" style="padding:24px;">
                    <div class="empty-desc">{{ __('usuarios.empty_team_members') }}</div>
                </div>
            @else
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('usuarios.col_member_name') }}</th>
                            <th>{{ __('usuarios.col_member_email') }}</th>
                            <th>{{ __('usuarios.col_member_role') }}</th>
                            <th style="text-align:right;">{{ __('usuarios.col_member_actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($miembros as $m)
                            <tr>
                                <td>{{ $m->name }}</td>
                                <td class="code-mono">{{ $m->email }}</td>
                                <td class="code-mono">{{ $m->rol_codigo ?? '—' }}</td>
                                <td style="text-align:right;">
                                    <button type="button" wire:click="quitarMiembro({{ $m->id }})"
                                            wire:confirm="{{ __('usuarios.confirm_remove_member', ['name' => $m->name]) }}"
                                            class="btn btn-ghost btn-sm" style="color:var(--danger-text);">{{ __('usuarios.btn_remove') }}</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endif
</div>
