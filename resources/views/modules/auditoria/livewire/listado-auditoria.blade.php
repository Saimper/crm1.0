<div class="space-y-4">
    <section class="rounded-lg border border-gray-200 bg-white p-4">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-3 text-sm">
            <div>
                <label class="block text-xs font-medium text-gray-700">Entidad</label>
                <select wire:model.live="entidadTipo" class="mt-1 block w-full border-gray-300 rounded-md text-sm">
                    <option value="">Todas</option>
                    @foreach($tiposEntidad as $t)
                        <option value="{{ $t }}">{{ $t }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700">Usuario</label>
                <select wire:model.live="usuarioId" class="mt-1 block w-full border-gray-300 rounded-md text-sm">
                    <option value="">Todos</option>
                    @foreach($usuarios as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700">Evento</label>
                <select wire:model.live="evento" class="mt-1 block w-full border-gray-300 rounded-md text-sm">
                    <option value="">Todos</option>
                    <option value="creado">Creado</option>
                    <option value="actualizado">Actualizado</option>
                    <option value="eliminado">Eliminado</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700">Desde</label>
                <input type="date" wire:model.live="desde" class="mt-1 block w-full border-gray-300 rounded-md text-sm"/>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700">Hasta</label>
                <input type="date" wire:model.live="hasta" class="mt-1 block w-full border-gray-300 rounded-md text-sm"/>
            </div>
        </div>
        <div class="mt-3 flex justify-end items-center gap-2">
            @php
                $pid = (int) app('tenancy.proyecto_activo')->id;
                $qs = array_filter([
                    'entidad_tipo' => $entidadTipo,
                    'usuario_id'   => $usuarioId,
                    'evento'       => $evento,
                    'desde'        => $desde,
                    'hasta'        => $hasta,
                ], fn ($v) => $v !== '' && $v !== null);
            @endphp
            <a href="{{ route('proyectos.auditoria.exportar', array_merge(['proyecto_id' => $pid], $qs)) }}"
               class="px-3 py-1.5 text-xs text-white bg-indigo-600 rounded hover:bg-indigo-700">
                Exportar CSV
            </a>
            <button type="button" wire:click="limpiarFiltros"
                    class="px-3 py-1.5 text-xs text-gray-700 border border-gray-300 rounded hover:bg-gray-50">
                Limpiar filtros
            </button>
        </div>
    </section>

    <section class="rounded-lg border border-gray-200 bg-white overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200 bg-gray-50 text-xs font-semibold uppercase tracking-wider text-gray-600">
            Eventos ({{ $registros->total() }})
        </div>
        @if($registros->isEmpty())
            <div class="p-6 text-sm text-gray-500 text-center">No hay eventos que coincidan con los filtros.</div>
        @else
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-600">
                    <tr>
                        <th class="px-3 py-2 text-left">Fecha</th>
                        <th class="px-3 py-2 text-left">Usuario</th>
                        <th class="px-3 py-2 text-left">Entidad</th>
                        <th class="px-3 py-2 text-left">ID</th>
                        <th class="px-3 py-2 text-left">Evento</th>
                        <th class="px-3 py-2 text-left">IP</th>
                        <th class="px-3 py-2 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($registros as $r)
                        @php
                            $badge = match ($r->evento) {
                                'creado'      => 'bg-emerald-100 text-emerald-800',
                                'actualizado' => 'bg-blue-100 text-blue-800',
                                'eliminado'   => 'bg-red-100 text-red-800',
                                default       => 'bg-gray-100 text-gray-700',
                            };
                        @endphp
                        <tr>
                            <td class="px-3 py-2 text-xs">{{ \Illuminate\Support\Carbon::parse($r->creada_en)->format('d/m/Y H:i:s') }}</td>
                            <td class="px-3 py-2 text-xs">{{ $r->usuario_nombre ?? '—' }}</td>
                            <td class="px-3 py-2 text-xs font-mono">{{ $r->entidad_tipo }}</td>
                            <td class="px-3 py-2 text-xs font-mono">{{ $r->entidad_id }}</td>
                            <td class="px-3 py-2">
                                <span class="inline-block rounded px-1.5 py-0.5 text-[10px] {{ $badge }}">{{ $r->evento }}</span>
                            </td>
                            <td class="px-3 py-2 text-xs font-mono text-gray-500">{{ $r->ip ?? '—' }}</td>
                            <td class="px-3 py-2 text-right">
                                <button type="button" wire:click="verDetalle({{ $r->id }})"
                                        class="text-xs text-indigo-700 hover:underline">Detalle</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="px-4 py-3 border-t border-gray-200 bg-gray-50">{{ $registros->links() }}</div>
        @endif
    </section>

    @if($detalle)
        <div class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" wire:click="cerrarDetalle">
            <div class="bg-white rounded-lg shadow-xl max-w-3xl w-full max-h-[85vh] overflow-y-auto"
                 wire:click.stop>
                <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                    <div>
                        <div class="text-xs text-gray-500">Auditoría #{{ $detalle->id }}</div>
                        <div class="text-sm font-semibold text-gray-800">
                            {{ $detalle->entidad_tipo }} · id {{ $detalle->entidad_id }} · {{ $detalle->evento }}
                        </div>
                    </div>
                    <button type="button" wire:click="cerrarDetalle"
                            class="text-gray-400 hover:text-gray-600">×</button>
                </div>
                <div class="p-4 space-y-4 text-xs">
                    <div class="text-gray-500">
                        {{ \Illuminate\Support\Carbon::parse($detalle->creada_en)->format('d/m/Y H:i:s') }}
                        · IP {{ $detalle->ip ?? '—' }}
                    </div>
                    @if($detalle->cambios)
                        <div>
                            <div class="font-semibold text-gray-700 mb-1">Cambios</div>
                            <pre class="bg-gray-50 border border-gray-200 rounded p-3 overflow-x-auto">{{ json_encode(json_decode($detalle->cambios, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                        </div>
                    @endif
                    @if($detalle->datos_antes)
                        <div>
                            <div class="font-semibold text-gray-700 mb-1">Antes</div>
                            <pre class="bg-gray-50 border border-gray-200 rounded p-3 overflow-x-auto">{{ json_encode(json_decode($detalle->datos_antes, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                        </div>
                    @endif
                    @if($detalle->datos_despues)
                        <div>
                            <div class="font-semibold text-gray-700 mb-1">Después</div>
                            <pre class="bg-gray-50 border border-gray-200 rounded p-3 overflow-x-auto">{{ json_encode(json_decode($detalle->datos_despues, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
