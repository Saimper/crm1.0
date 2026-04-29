<div class="space-y-4">
    @if(session('admin-usuarios-ok'))
        <div class="rounded border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
            {{ session('admin-usuarios-ok') }}
        </div>
    @endif
    @if(session('admin-usuarios-error'))
        <div class="rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
            {{ session('admin-usuarios-error') }}
        </div>
    @endif

    <div class="flex items-center justify-between">
        <div class="text-xs text-gray-500">
            Total: <span class="font-semibold text-gray-800">{{ $usuarios->count() }}</span>
        </div>
        <button type="button" wire:click="abrirFormCrearUsuario"
                class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">
            Nuevo usuario
        </button>
    </div>

    <div class="space-y-3">
        @foreach($usuarios as $u)
            <div class="rounded-md border border-gray-200 bg-white p-4">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <div class="font-semibold text-gray-900">{{ $u->name }}</div>
                            @if($u->activo)
                                <span class="inline-block rounded px-2 py-0.5 text-xs bg-emerald-100 text-emerald-800">activo</span>
                            @else
                                <span class="inline-block rounded px-2 py-0.5 text-xs bg-gray-100 text-gray-600">inactivo</span>
                            @endif
                            @if($u->es_admin_global)
                                <span class="inline-block rounded px-2 py-0.5 text-xs bg-red-100 text-red-800 font-semibold">ADMIN_GLOBAL</span>
                            @endif
                        </div>
                        <div class="text-xs text-gray-500 mt-0.5 font-mono">{{ $u->email }}</div>
                    </div>
                    <div class="flex items-center gap-2 text-xs">
                        <button type="button" wire:click="abrirFormEditarUsuario({{ $u->id }})"
                                class="text-indigo-700 hover:underline">Editar</button>
                        @if($u->es_admin_global)
                            <button type="button" wire:click="revocarAdminGlobal({{ $u->id }})"
                                    wire:confirm="¿Revocar ADMIN_GLOBAL a este usuario?"
                                    class="text-red-700 hover:underline">Revocar admin</button>
                        @else
                            <button type="button" wire:click="promoverAdminGlobal({{ $u->id }})"
                                    wire:confirm="¿Promover a ADMIN_GLOBAL? Tendrá acceso cross-project total."
                                    class="text-amber-700 hover:underline">Promover admin</button>
                        @endif
                        <button type="button" wire:click="abrirFormAsignacion({{ $u->id }})"
                                class="text-emerald-700 hover:underline">Asignar rol</button>
                    </div>
                </div>

                @if(isset($asignaciones[$u->id]) && $asignaciones[$u->id]->isNotEmpty())
                    <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                        @foreach($asignaciones[$u->id] as $a)
                            <div class="rounded border border-gray-200 px-3 py-2 text-xs">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <div class="font-medium text-gray-800">{{ $a->proyecto_codigo }}</div>
                                        <div class="text-[10px] text-gray-500">{{ $a->proyecto_nombre }} · {{ $a->tipo_operacion }}</div>
                                        <div class="mt-1">
                                            <span class="inline-block rounded px-1.5 py-0.5 text-[10px] bg-indigo-100 text-indigo-800 font-semibold">
                                                {{ $a->rol_codigo }}
                                            </span>
                                        </div>
                                    </div>
                                    <button type="button"
                                            wire:click="quitarAsignacion({{ $a->usuario_id }}, {{ $a->proyecto_id }}, {{ $a->rol_id }})"
                                            wire:confirm="¿Quitar esta asignación?"
                                            class="text-[10px] text-red-700 hover:underline">Quitar</button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="mt-3 text-[11px] text-gray-500">Sin asignaciones por proyecto.</div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Form usuario --}}
    @if($formUsuarioVisible)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/40" wire:key="form-usuario">
            <div class="bg-white rounded-lg shadow-lg w-full max-w-lg p-6 space-y-3">
                <div class="text-lg font-semibold text-gray-900">
                    {{ $editandoUsuarioId === null ? 'Nuevo usuario' : 'Editar usuario' }}
                </div>

                <div class="space-y-3 text-sm">
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Nombre</label>
                        <input type="text" wire:model="formUsuario.name"
                               class="mt-1 block w-full text-sm rounded border-gray-300"/>
                        @error('formUsuario.name')<div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Correo</label>
                        <input type="email" wire:model="formUsuario.email"
                               class="mt-1 block w-full text-sm rounded border-gray-300"/>
                        @error('formUsuario.email')<div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700">
                            Contraseña {{ $editandoUsuarioId !== null ? '(dejar vacía para no cambiar)' : '' }}
                        </label>
                        <input type="password" wire:model="formUsuario.password"
                               class="mt-1 block w-full text-sm rounded border-gray-300"/>
                        @error('formUsuario.password')<div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>@enderror
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" wire:model="formUsuario.activo" class="rounded"/>
                        <span>Activo</span>
                    </label>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" wire:click="cerrarFormUsuario"
                            class="px-3 py-1.5 text-xs text-gray-700 border border-gray-300 rounded hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button type="button" wire:click="guardarUsuario"
                            class="px-3 py-1.5 text-xs text-white bg-indigo-600 rounded hover:bg-indigo-700">
                        Guardar
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Form asignación --}}
    @if($formAsignacionVisible)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/40" wire:key="form-asignacion">
            <div class="bg-white rounded-lg shadow-lg w-full max-w-md p-6 space-y-3">
                <div class="text-lg font-semibold text-gray-900">Asignar rol en proyecto</div>

                <div class="space-y-3 text-sm">
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Proyecto</label>
                        <select wire:model="asignarProyectoId"
                                class="mt-1 block w-full text-sm rounded border-gray-300">
                            <option value="">—</option>
                            @foreach($proyectos as $p)
                                <option value="{{ $p->id }}">{{ $p->codigo }} — {{ $p->nombre }}</option>
                            @endforeach
                        </select>
                        @error('asignarProyectoId')<div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Rol</label>
                        <select wire:model="asignarRolId"
                                class="mt-1 block w-full text-sm rounded border-gray-300">
                            <option value="">—</option>
                            @foreach($roles as $r)
                                <option value="{{ $r->id }}">{{ $r->codigo }} — {{ $r->nombre }}</option>
                            @endforeach
                        </select>
                        @error('asignarRolId')<div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" wire:click="cerrarFormAsignacion"
                            class="px-3 py-1.5 text-xs text-gray-700 border border-gray-300 rounded hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button type="button" wire:click="guardarAsignacion"
                            class="px-3 py-1.5 text-xs text-white bg-indigo-600 rounded hover:bg-indigo-700">
                        Asignar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
