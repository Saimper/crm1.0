<div class="space-y-4">
    <div class="card card-pad flex items-center justify-between">
        <div>
            <h3 style="font-size:13px;font-weight:600;color:var(--text);">Equipos</h3>
            <p style="font-size:12px;color:var(--text-tertiary);margin-top:4px;">
                Agrupa supervisores, gestores y auditores del proyecto para reportería y asignaciones.
            </p>
        </div>
        @if(! $formEquipoVisible)
            <button type="button" wire:click="abrirFormCrear" class="btn btn-primary btn-sm">
                <x-ui.icon name="plus" :size="13" />
                <span>Nuevo equipo</span>
            </button>
        @endif
    </div>

    @if($formEquipoVisible)
        <div class="card card-pad" style="background:var(--primary-soft);border-color:var(--primary-soft-border);">
            <h4 style="font-size:13px;font-weight:600;color:var(--primary-text);margin-bottom:12px;">
                {{ $equipoEditandoId === null ? 'Crear equipo' : 'Editar equipo' }}
            </h4>
            <form wire:submit.prevent="guardarEquipo" class="grid grid-cols-1 md:grid-cols-4 gap-3">
                <div class="field">
                    <label class="field-label">Código</label>
                    <input type="text" wire:model="formCodigo"
                           class="input font-mono uppercase @error('formCodigo') input-error @enderror"
                           placeholder="EQ_COBRANZA"/>
                    @error('formCodigo')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="field md:col-span-2">
                    <label class="field-label">Nombre</label>
                    <input type="text" wire:model="formNombre"
                           class="input @error('formNombre') input-error @enderror"
                           placeholder="Equipo de cobranza mañana"/>
                    @error('formNombre')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="field">
                    <label class="field-label">Activo</label>
                    <select wire:model="formActivo" class="select">
                        <option value="1">Sí</option>
                        <option value="0">No</option>
                    </select>
                </div>
                <div class="field md:col-span-4">
                    <label class="field-label">Descripción (opcional)</label>
                    <textarea wire:model="formDescripcion" rows="2" class="textarea @error('formDescripcion') input-error @enderror"></textarea>
                    @error('formDescripcion')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="md:col-span-4 flex items-center justify-end gap-2">
                    <button type="button" wire:click="cerrarFormEquipo" class="btn btn-secondary">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    @endif

    <div class="card">
        @if($equipos->isEmpty())
            <div class="empty">
                <div class="empty-icon"><x-ui.icon name="briefcase" :size="32" /></div>
                <div class="empty-title">Sin equipos</div>
                <div class="empty-desc">Aún no hay equipos en este proyecto.</div>
            </div>
        @else
            <table class="table">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th class="num">Miembros</th>
                        <th style="text-align:center;">Estado</th>
                        <th style="text-align:right;">Acciones</th>
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
                                    <span class="badge badge-success">Activo</span>
                                @else
                                    <span class="badge badge-neutral">Inactivo</span>
                                @endif
                            </td>
                            <td style="text-align:right;">
                                <button type="button" wire:click="gestionarMiembros({{ $e->id }})" class="btn btn-ghost btn-sm">Miembros</button>
                                <button type="button" wire:click="abrirFormEditar({{ $e->id }})" class="btn btn-ghost btn-sm">Editar</button>
                                @if($e->activo)
                                    <button type="button" wire:click="desactivar({{ $e->id }})"
                                            class="btn btn-ghost btn-sm" style="color:var(--danger-text);">Desactivar</button>
                                @else
                                    <button type="button" wire:click="activar({{ $e->id }})"
                                            class="btn btn-ghost btn-sm" style="color:var(--success-text);">Activar</button>
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
                <h4 style="font-size:13px;font-weight:600;color:var(--text);">Miembros del equipo</h4>
                <button type="button" wire:click="cerrarMiembros" class="btn btn-ghost btn-sm">
                    <x-ui.icon name="x" :size="13" />
                    <span>Cerrar</span>
                </button>
            </div>

            <form wire:submit.prevent="buscarUsuario" class="flex items-end gap-2">
                <div class="field flex-1" style="margin-bottom:0;">
                    <label class="field-label">Email del usuario</label>
                    <input type="email" wire:model="buscarEmail"
                           class="input @error('buscarEmail') input-error @enderror"
                           placeholder="correo@empresa.com"/>
                    @error('buscarEmail')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <button type="submit" class="btn btn-secondary">Buscar</button>
                @if($usuarioBuscadoId !== null)
                    <button type="button" wire:click="agregarMiembro" class="btn btn-primary">
                        Agregar {{ $usuarioBuscadoNombre }}
                    </button>
                @endif
            </form>

            @if($miembros->isEmpty())
                <div class="empty" style="padding:24px;">
                    <div class="empty-desc">Este equipo aún no tiene miembros.</div>
                </div>
            @else
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>Rol en proyecto</th>
                            <th style="text-align:right;">Acciones</th>
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
                                            wire:confirm="¿Quitar a {{ $m->name }} del equipo?"
                                            class="btn btn-ghost btn-sm" style="color:var(--danger-text);">Quitar</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endif
</div>
