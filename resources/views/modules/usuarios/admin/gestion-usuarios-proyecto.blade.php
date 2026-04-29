<div class="space-y-4">
    @if(session('gestion-usuarios-ok'))
        <div class="rounded border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
            {{ session('gestion-usuarios-ok') }}
        </div>
    @endif
    @if(session('gestion-usuarios-error'))
        <div class="rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
            {{ session('gestion-usuarios-error') }}
        </div>
    @endif

    <div class="flex items-center justify-between">
        <div class="text-xs text-gray-500">
            Usuarios con rol en este proyecto:
            <span class="font-semibold text-gray-800">{{ $asignaciones->count() }}</span>
        </div>
        <button type="button" wire:click="abrirFormAsignar"
                class="inline-flex items-center px-3 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded hover:bg-indigo-700">
            Asignar usuario
        </button>
    </div>

    <div class="space-y-3">
        @if($asignaciones->isEmpty())
            <div class="rounded-md border border-dashed border-gray-300 bg-gray-50 p-6 text-center text-sm text-gray-500">
                Este proyecto aún no tiene usuarios asignados (aparte de ADMIN_GLOBAL).
            </div>
        @else
            @foreach($asignaciones as $usuarioId => $rolesUsuario)
                @php $primero = $rolesUsuario->first(); @endphp
                <div class="rounded-md border border-gray-200 bg-white p-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <div class="font-semibold text-gray-900">{{ $primero->name }}</div>
                                @if(! $primero->usuario_activo)
                                    <span class="inline-block rounded px-2 py-0.5 text-xs bg-gray-100 text-gray-600">inactivo</span>
                                @endif
                                @if((int) $usuarioId === $usuarioActualId)
                                    <span class="inline-block rounded px-2 py-0.5 text-[10px] bg-indigo-100 text-indigo-800">tú</span>
                                @endif
                            </div>
                            <div class="text-xs text-gray-500 mt-0.5 font-mono">{{ $primero->email }}</div>
                            <div class="mt-2 space-y-1">
                                @foreach($rolesUsuario as $a)
                                    @php
                                        $badge = match ($a->rol_codigo) {
                                            'SUPERVISOR' => 'bg-violet-100 text-violet-800',
                                            'GESTOR'     => 'bg-emerald-100 text-emerald-800',
                                            'AUDITOR'    => 'bg-amber-100 text-amber-800',
                                            default      => 'bg-gray-100 text-gray-700',
                                        };
                                        $claveRestr = $a->usuario_id.'-'.$a->rol_id;
                                        $carterasRol = $restricciones->get($claveRestr, collect());
                                    @endphp
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="inline-flex items-center gap-1 rounded px-2 py-0.5 text-xs font-semibold {{ $badge }}">
                                            {{ $a->rol_codigo }}
                                            <button type="button"
                                                    wire:click="quitar({{ $a->usuario_id }}, {{ $a->rol_id }})"
                                                    wire:confirm="¿Quitar el rol {{ $a->rol_codigo }} a {{ $primero->name }}?"
                                                    class="text-xs leading-none hover:text-red-700"
                                                    title="Quitar rol">×</button>
                                        </span>
                                        @if($carterasRol->isEmpty())
                                            <span class="text-[10px] uppercase tracking-wider text-gray-500">todo el proyecto</span>
                                        @else
                                            <span class="text-[10px] uppercase tracking-wider text-gray-500">carteras:</span>
                                            @foreach($carterasRol as $cr)
                                                <span class="inline-block rounded bg-gray-100 px-1.5 py-0.5 text-[10px] text-gray-700 font-mono">
                                                    {{ $cr->cartera_nombre }}
                                                </span>
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
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/40" wire:key="form-asignar">
            <div class="bg-white rounded-lg shadow-lg w-full max-w-md p-6 space-y-3">
                <div class="text-lg font-semibold text-gray-900">Asignar rol</div>

                <div class="space-y-3 text-sm">
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Correo del usuario</label>
                        <div class="mt-1 flex items-center gap-2">
                            <input type="email" wire:model="buscarEmail"
                                   class="block w-full text-sm rounded border-gray-300"
                                   placeholder="usuario@dominio.com"/>
                            <button type="button" wire:click="buscarUsuario"
                                    class="px-3 py-1.5 text-xs text-white bg-gray-700 rounded hover:bg-gray-800 whitespace-nowrap">
                                Buscar
                            </button>
                        </div>
                        @error('buscarEmail')<div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>@enderror
                    </div>

                    @if($usuarioBuscadoId !== null)
                        <div class="rounded border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-800">
                            Usuario encontrado: <span class="font-semibold">{{ $usuarioBuscadoNombre }}</span>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-700">Rol</label>
                            <select wire:model="rolAsignarId"
                                    class="mt-1 block w-full text-sm rounded border-gray-300">
                                <option value="">—</option>
                                @foreach($rolesAsignables as $r)
                                    <option value="{{ $r->id }}">{{ $r->codigo }} — {{ $r->nombre }}</option>
                                @endforeach
                            </select>
                            @error('rolAsignarId')<div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>@enderror
                        </div>

                        @if($carterasDelProyecto->isNotEmpty())
                            <div>
                                <label class="block text-xs font-medium text-gray-700">Restringir a carteras (opcional)</label>
                                <p class="text-[11px] text-gray-500 mt-0.5">
                                    Si no seleccionas ninguna, el rol aplica a todo el proyecto.
                                </p>
                                <div class="mt-2 space-y-1 max-h-40 overflow-y-auto border border-gray-200 rounded p-2">
                                    @foreach($carterasDelProyecto as $c)
                                        <label class="flex items-center gap-2 text-xs">
                                            <input type="checkbox" value="{{ $c->id }}"
                                                   wire:model="carterasSeleccionadas"
                                                   class="rounded border-gray-300"/>
                                            <span class="font-mono text-gray-500">{{ $c->codigo }}</span>
                                            <span>{{ $c->nombre }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endif
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button wire:click="cerrarFormAsignar"
                            class="px-3 py-1.5 text-xs border border-gray-300 rounded hover:bg-gray-50">
                        Cancelar
                    </button>
                    @if($usuarioBuscadoId !== null)
                        <button wire:click="asignar"
                                class="px-3 py-1.5 text-xs text-white bg-indigo-600 rounded hover:bg-indigo-700">
                            Asignar
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
