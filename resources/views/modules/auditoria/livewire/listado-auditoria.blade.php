<div class="space-y-4">
    <section class="rounded-lg border border-ink-200 bg-white p-4">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-3 text-sm">
            <div>
                <label class="block text-xs font-medium text-ink-700">Entidad</label>
                <select wire:model.live="entidadTipo" class="mt-1 block w-full border-ink-300 rounded-md text-sm">
                    <option value="">Todas</option>
                    @foreach($tiposEntidad as $t)
                        <option value="{{ $t }}">{{ $t }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-ink-700">Usuario</label>
                <select wire:model.live="usuarioId" class="mt-1 block w-full border-ink-300 rounded-md text-sm">
                    <option value="">Todos</option>
                    @foreach($usuarios as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-ink-700">Evento</label>
                <select wire:model.live="evento" class="mt-1 block w-full border-ink-300 rounded-md text-sm">
                    <option value="">Todos</option>
                    <option value="creado">Creado</option>
                    <option value="actualizado">Actualizado</option>
                    <option value="eliminado">Eliminado</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-ink-700">Desde</label>
                <input type="date" wire:model.live="desde" class="mt-1 block w-full border-ink-300 rounded-md text-sm"/>
            </div>
            <div>
                <label class="block text-xs font-medium text-ink-700">Hasta</label>
                <input type="date" wire:model.live="hasta" class="mt-1 block w-full border-ink-300 rounded-md text-sm"/>
            </div>
        </div>
        <div class="mt-3 flex justify-end items-center gap-2">
            @php
                $qs = array_filter([
                    'entidad_tipo' => $entidadTipo,
                    'usuario_id'   => $usuarioId,
                    'evento'       => $evento,
                    'desde'        => $desde,
                    'hasta'        => $hasta,
                ], fn ($v) => $v !== '' && $v !== null);
            @endphp
            @if(! $modoGlobal)
                @php $pid = (int) app('tenancy.proyecto_activo')->id; @endphp
                <a href="{{ route('proyectos.auditoria.exportar', array_merge(['proyecto_id' => $pid], $qs)) }}"
                   class="px-3 py-1.5 text-xs text-white bg-brand-600 rounded hover:bg-brand-700">
                    Exportar CSV
                </a>
            @endif
            <button type="button" wire:click="limpiarFiltros"
                    class="px-3 py-1.5 text-xs text-ink-700 border border-ink-300 rounded hover:bg-ink-50">
                Limpiar filtros
            </button>
        </div>
    </section>

    <section class="rounded-lg border border-ink-200 bg-white overflow-hidden">
        <div class="px-4 py-3 border-b border-ink-200 bg-ink-50 text-xs font-semibold uppercase tracking-wider text-ink-600">
            Eventos ({{ $registros->total() }})
        </div>
        @if($registros->isEmpty())
            <div class="p-6 text-sm text-ink-500 text-center">No hay eventos que coincidan con los filtros.</div>
        @else
            <table class="min-w-full divide-y divide-ink-200 text-sm">
                <thead class="bg-ink-50 text-xs uppercase tracking-wider text-ink-600">
                    <tr>
                        <th class="px-3 py-2 text-left">Fecha</th>
                        @if($modoGlobal)<th class="px-3 py-2 text-left">Proyecto</th>@endif
                        <th class="px-3 py-2 text-left">Usuario</th>
                        <th class="px-3 py-2 text-left">Entidad</th>
                        <th class="px-3 py-2 text-left">ID</th>
                        <th class="px-3 py-2 text-left">Evento</th>
                        <th class="px-3 py-2 text-left">IP</th>
                        <th class="px-3 py-2 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    @foreach($registros as $r)
                        @php
                            $badge = match ($r->evento) {
                                'creado'      => 'bg-success-50 text-success-800',
                                'actualizado' => 'bg-brand-100 text-brand-800',
                                'eliminado'   => 'bg-danger-50 text-danger-700',
                                default       => 'bg-ink-100 text-ink-700',
                            };
                        @endphp
                        <tr>
                            <td class="px-3 py-2 text-xs">{{ \Illuminate\Support\Carbon::parse($r->creada_en)->format('d/m/Y H:i:s') }}</td>
                            @if($modoGlobal)
                                <td class="px-3 py-2 text-xs">
                                    @if($r->proyecto_id)
                                        <span class="font-mono text-ink-600">{{ $r->proyecto_codigo }}</span>
                                    @else
                                        <span class="text-ink-400 italic">global</span>
                                    @endif
                                </td>
                            @endif
                            <td class="px-3 py-2 text-xs">{{ $r->usuario_nombre ?? '—' }}</td>
                            <td class="px-3 py-2 text-xs font-mono">{{ $r->entidad_tipo }}</td>
                            <td class="px-3 py-2 text-xs font-mono">{{ $r->entidad_id }}</td>
                            <td class="px-3 py-2">
                                <span class="inline-block rounded px-1.5 py-0.5 text-[10px] {{ $badge }}">{{ $r->evento }}</span>
                            </td>
                            <td class="px-3 py-2 text-xs font-mono text-ink-500">{{ $r->ip ?? '—' }}</td>
                            <td class="px-3 py-2 text-right">
                                <button type="button" wire:click="verDetalle({{ $r->id }})"
                                        class="text-xs text-brand-700 hover:underline">Detalle</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="px-4 py-3 border-t border-ink-200 bg-ink-50">{{ $registros->links() }}</div>
        @endif
    </section>

    @if($detalle)
        <div class="fixed inset-0 bg-ink-900/40 z-50 flex items-center justify-center p-4" wire:click="cerrarDetalle">
            <div class="bg-white rounded-lg shadow-xl max-w-3xl w-full max-h-[85vh] overflow-y-auto"
                 wire:click.stop>
                <div class="px-4 py-3 border-b border-ink-200 flex items-center justify-between">
                    <div>
                        <div class="text-xs text-ink-500">Auditoría #{{ $detalle->id }}</div>
                        <div class="text-sm font-semibold text-ink-800">
                            {{ $detalle->entidad_tipo }} · id {{ $detalle->entidad_id }} · {{ $detalle->evento }}
                        </div>
                    </div>
                    <button type="button" wire:click="cerrarDetalle"
                            class="text-ink-400 hover:text-ink-600">×</button>
                </div>
                <div class="p-4 space-y-4 text-xs">
                    <div class="text-ink-500">
                        {{ \Illuminate\Support\Carbon::parse($detalle->creada_en)->format('d/m/Y H:i:s') }}
                        · IP {{ $detalle->ip ?? '—' }}
                    </div>
                    @php
                        $cambiosArr = $detalle->cambios ? json_decode((string) $detalle->cambios, true) : null;
                        $antesArr = $detalle->datos_antes ? json_decode((string) $detalle->datos_antes, true) : null;
                        $despuesArr = $detalle->datos_despues ? json_decode((string) $detalle->datos_despues, true) : null;
                        $fmtVal = function ($v) {
                            if ($v === null) return '—';
                            if (is_bool($v)) return $v ? 'true' : 'false';
                            if (is_array($v)) return json_encode($v, JSON_UNESCAPED_UNICODE);
                            return (string) $v;
                        };
                    @endphp
                    @if(is_array($cambiosArr) && $cambiosArr !== [])
                        <div>
                            <div class="font-semibold text-ink-700 mb-2">Cambios ({{ count($cambiosArr) }})</div>
                            <table class="min-w-full divide-y divide-ink-200 border border-ink-200 rounded">
                                <thead class="bg-ink-50">
                                    <tr>
                                        <th class="px-2 py-1.5 text-left text-[10px] uppercase tracking-wider text-ink-600">Campo</th>
                                        <th class="px-2 py-1.5 text-left text-[10px] uppercase tracking-wider text-danger-700">Antes</th>
                                        <th class="px-2 py-1.5 text-left text-[10px] uppercase tracking-wider text-success-700">Después</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-ink-100">
                                    @foreach($cambiosArr as $campo => $par)
                                        <tr>
                                            <td class="px-2 py-1.5 font-mono text-ink-900">{{ $campo }}</td>
                                            <td class="px-2 py-1.5 font-mono text-danger-700 break-all">{{ $fmtVal($par['antes'] ?? null) }}</td>
                                            <td class="px-2 py-1.5 font-mono text-success-700 break-all">{{ $fmtVal($par['despues'] ?? null) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @elseif(is_array($antesArr) || is_array($despuesArr))
                        <div>
                            <div class="font-semibold text-ink-700 mb-2">Snapshot</div>
                            <table class="min-w-full divide-y divide-ink-200 border border-ink-200 rounded">
                                <thead class="bg-ink-50">
                                    <tr>
                                        <th class="px-2 py-1.5 text-left text-[10px] uppercase tracking-wider text-ink-600">Campo</th>
                                        <th class="px-2 py-1.5 text-left text-[10px] uppercase tracking-wider text-danger-700">Antes</th>
                                        <th class="px-2 py-1.5 text-left text-[10px] uppercase tracking-wider text-success-700">Después</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-ink-100">
                                    @php
                                        $campos = array_unique(array_merge(
                                            is_array($antesArr) ? array_keys($antesArr) : [],
                                            is_array($despuesArr) ? array_keys($despuesArr) : [],
                                        ));
                                    @endphp
                                    @foreach($campos as $campo)
                                        <tr>
                                            <td class="px-2 py-1.5 font-mono text-ink-900">{{ $campo }}</td>
                                            <td class="px-2 py-1.5 font-mono text-danger-700 break-all">{{ $fmtVal($antesArr[$campo] ?? null) }}</td>
                                            <td class="px-2 py-1.5 font-mono text-success-700 break-all">{{ $fmtVal($despuesArr[$campo] ?? null) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-ink-500 italic">Sin datos de diff registrados.</div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
