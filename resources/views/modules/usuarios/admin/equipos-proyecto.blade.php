<div class="space-y-6">
    <section class="rounded-lg border border-gray-200 bg-white p-4 flex items-center justify-between">
        <div>
            <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-700">Equipos</h3>
            <p class="text-xs text-gray-500 mt-1">Agrupa supervisores, gestores y auditores del proyecto para reportería y asignaciones.</p>
        </div>
        @if(! $formEquipoVisible)
            <button type="button" wire:click="abrirFormCrear"
                    class="px-3 py-1.5 text-xs text-white bg-indigo-600 rounded hover:bg-indigo-700">
                Nuevo equipo
            </button>
        @endif
    </section>

    @if($formEquipoVisible)
        <section class="rounded-lg border border-indigo-200 bg-indigo-50 p-4">
            <h4 class="text-sm font-semibold text-indigo-900 mb-3">
                {{ $equipoEditandoId === null ? 'Crear equipo' : 'Editar equipo' }}
            </h4>
            <form wire:submit.prevent="guardarEquipo" class="grid grid-cols-1 md:grid-cols-4 gap-3 text-sm">
                <div>
                    <label class="block text-xs font-medium text-gray-700">Código</label>
                    <input type="text" wire:model="formCodigo"
                           class="mt-1 block w-full border-gray-300 rounded-md text-sm font-mono uppercase"
                           placeholder="EQ_COBRANZA"/>
                    @error('formCodigo')<div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>@enderror
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-gray-700">Nombre</label>
                    <input type="text" wire:model="formNombre"
                           class="mt-1 block w-full border-gray-300 rounded-md text-sm"
                           placeholder="Equipo de cobranza mañana"/>
                    @error('formNombre')<div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">Activo</label>
                    <select wire:model="formActivo" class="mt-1 block w-full border-gray-300 rounded-md text-sm">
                        <option value="1">Sí</option>
                        <option value="0">No</option>
                    </select>
                </div>
                <div class="md:col-span-4">
                    <label class="block text-xs font-medium text-gray-700">Descripción (opcional)</label>
                    <textarea wire:model="formDescripcion" rows="2"
                              class="mt-1 block w-full border-gray-300 rounded-md text-sm"></textarea>
                    @error('formDescripcion')<div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>@enderror
                </div>
                <div class="md:col-span-4 flex items-center justify-end gap-2">
                    <button type="button" wire:click="cerrarFormEquipo"
                            class="px-3 py-1.5 text-xs text-gray-700 border border-gray-300 rounded hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button type="submit"
                            class="px-3 py-1.5 text-xs text-white bg-indigo-600 rounded hover:bg-indigo-700">
                        Guardar
                    </button>
                </div>
            </form>
        </section>
    @endif

    <section class="rounded-lg border border-gray-200 bg-white overflow-hidden">
        @if($equipos->isEmpty())
            <div class="p-6 text-sm text-gray-500 text-center">Aún no hay equipos en este proyecto.</div>
        @else
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-600">
                    <tr>
                        <th class="px-3 py-2 text-left">Código</th>
                        <th class="px-3 py-2 text-left">Nombre</th>
                        <th class="px-3 py-2 text-left">Descripción</th>
                        <th class="px-3 py-2 text-right">Miembros</th>
                        <th class="px-3 py-2 text-center">Estado</th>
                        <th class="px-3 py-2 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($equipos as $e)
                        <tr>
                            <td class="px-3 py-2 font-mono">{{ $e->codigo }}</td>
                            <td class="px-3 py-2">{{ $e->nombre }}</td>
                            <td class="px-3 py-2 text-xs text-gray-500">{{ $e->descripcion }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ $e->miembros_count }}</td>
                            <td class="px-3 py-2 text-center">
                                @if($e->activo)
                                    <span class="inline-block rounded bg-emerald-100 text-emerald-800 px-1.5 py-0.5 text-[10px]">Activo</span>
                                @else
                                    <span class="inline-block rounded bg-gray-200 text-gray-700 px-1.5 py-0.5 text-[10px]">Inactivo</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right text-xs space-x-3">
                                <button type="button" wire:click="gestionarMiembros({{ $e->id }})"
                                        class="text-indigo-700 hover:underline">Miembros</button>
                                <button type="button" wire:click="abrirFormEditar({{ $e->id }})"
                                        class="text-gray-700 hover:underline">Editar</button>
                                @if($e->activo)
                                    <button type="button" wire:click="desactivar({{ $e->id }})"
                                            class="text-red-600 hover:underline">Desactivar</button>
                                @else
                                    <button type="button" wire:click="activar({{ $e->id }})"
                                            class="text-emerald-700 hover:underline">Activar</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    @if($gestionandoEquipoId !== null)
        <section class="rounded-lg border border-indigo-200 bg-white p-4 space-y-3">
            <div class="flex items-center justify-between">
                <h4 class="text-sm font-semibold text-gray-800">Miembros del equipo</h4>
                <button type="button" wire:click="cerrarMiembros"
                        class="text-xs text-gray-500 hover:text-gray-700">× Cerrar</button>
            </div>

            <form wire:submit.prevent="buscarUsuario" class="flex items-end gap-2">
                <div class="flex-1">
                    <label class="block text-xs font-medium text-gray-700">Email del usuario</label>
                    <input type="email" wire:model="buscarEmail"
                           class="mt-1 block w-full border-gray-300 rounded-md text-sm"
                           placeholder="correo@empresa.com"/>
                    @error('buscarEmail')<div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>@enderror
                </div>
                <button type="submit"
                        class="px-3 py-2 text-xs text-white bg-gray-700 rounded hover:bg-gray-800">
                    Buscar
                </button>
                @if($usuarioBuscadoId !== null)
                    <button type="button" wire:click="agregarMiembro"
                            class="px-3 py-2 text-xs text-white bg-indigo-600 rounded hover:bg-indigo-700">
                        Agregar {{ $usuarioBuscadoNombre }}
                    </button>
                @endif
            </form>

            @if($miembros->isEmpty())
                <div class="text-sm text-gray-500 text-center py-4">Este equipo aún no tiene miembros.</div>
            @else
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-600">
                        <tr>
                            <th class="px-3 py-2 text-left">Nombre</th>
                            <th class="px-3 py-2 text-left">Email</th>
                            <th class="px-3 py-2 text-left">Rol en proyecto</th>
                            <th class="px-3 py-2 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($miembros as $m)
                            <tr>
                                <td class="px-3 py-2">{{ $m->name }}</td>
                                <td class="px-3 py-2 text-xs">{{ $m->email }}</td>
                                <td class="px-3 py-2 text-xs font-mono">{{ $m->rol_codigo ?? '—' }}</td>
                                <td class="px-3 py-2 text-right text-xs">
                                    <button type="button" wire:click="quitarMiembro({{ $m->id }})"
                                            wire:confirm="¿Quitar a {{ $m->name }} del equipo?"
                                            class="text-red-600 hover:underline">Quitar</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    @endif
</div>
