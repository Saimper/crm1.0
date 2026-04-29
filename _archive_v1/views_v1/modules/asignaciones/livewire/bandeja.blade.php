<div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

    @if($mensajeExito)
        <div class="rounded-md bg-emerald-50 border border-emerald-200 px-4 py-2 text-sm text-emerald-800"
             x-data="{}" x-init="setTimeout(() => $wire.set('mensajeExito', null), 3000)">
            {{ $mensajeExito }}
        </div>
    @endif

    @error('asignacion')
        <div class="rounded-md bg-red-50 border border-red-200 px-4 py-2 text-sm text-red-800">
            {{ $message }}
        </div>
    @enderror

    {{-- Resumen de conteos --}}
    <section class="bg-white shadow rounded-lg p-4">
        <div class="flex flex-wrap items-center gap-3 text-sm">
            @php
                $chips = [
                    'todos'      => ['Todas', $totalGeneral],
                    'pendiente'  => ['Pendientes', (int) ($conteoPorEstado['pendiente']  ?? 0)],
                    'en_trabajo' => ['En trabajo', (int) ($conteoPorEstado['en_trabajo'] ?? 0)],
                    'cerrada'    => ['Cerradas',   (int) ($conteoPorEstado['cerrada']    ?? 0)],
                ];
            @endphp
            @foreach($chips as $valor => [$label, $total])
                @php $activo = $estadoFiltro === $valor; @endphp
                <button type="button"
                        wire:click="$set('estadoFiltro', '{{ $valor }}')"
                        class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-medium transition {{ $activo
                            ? 'border-indigo-600 bg-indigo-600 text-white'
                            : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50' }}">
                    {{ $label }}
                    <span class="inline-flex items-center justify-center rounded-full {{ $activo ? 'bg-white/20' : 'bg-gray-100' }} px-1.5">{{ $total }}</span>
                </button>
            @endforeach

            <div class="ml-auto w-full sm:w-80">
                <input type="text"
                       wire:model.live.debounce.300ms="busqueda"
                       placeholder="Buscar identificación, nombre o préstamo..."
                       class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
        </div>
    </section>

    {{-- Tabla --}}
    <section class="bg-white shadow rounded-lg overflow-hidden">
        @if($asignaciones->isEmpty())
            <div class="p-12 text-center text-sm text-gray-500">
                No hay asignaciones que mostrar con los filtros actuales.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wider text-gray-600">
                            <th class="px-4 py-2">Prio</th>
                            <th class="px-4 py-2">Cliente</th>
                            <th class="px-4 py-2">Préstamo</th>
                            <th class="px-4 py-2 text-right">Saldo</th>
                            <th class="px-4 py-2">Mora</th>
                            <th class="px-4 py-2">Última gestión</th>
                            <th class="px-4 py-2">Estado</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($asignaciones as $a)
                            @php
                                $nombre = $a->tipo_persona === 'juridica'
                                    ? (string) $a->razon_social
                                    : trim((string) ($a->nombres ?? '').' '.(string) ($a->apellidos ?? ''));
                                $mora = (int) $a->dias_mora;
                                $moraColor = $mora >= 90 ? 'bg-red-100 text-red-800'
                                    : ($mora >= 31 ? 'bg-amber-100 text-amber-800'
                                    : ($mora > 0 ? 'bg-yellow-100 text-yellow-800' : 'bg-emerald-100 text-emerald-800'));
                            @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <span class="inline-block rounded bg-indigo-50 text-indigo-800 text-xs font-semibold px-2 py-0.5">{{ $a->prioridad }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-900">{{ $nombre }}</div>
                                    <div class="text-xs text-gray-500">{{ $a->identificacion }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-900">{{ $a->numero_prestamo }}</div>
                                    <div class="text-xs text-gray-500">{{ $a->estado_producto_nombre }}</div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="font-semibold text-gray-900">{{ $a->moneda }} {{ number_format((float) $a->saldo_total, 2) }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-block rounded px-2 py-0.5 text-xs font-medium {{ $moraColor }}">
                                        {{ $mora > 0 ? $mora.' días' : 'al día' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    @if($a->fecha_ultima_gestion)
                                        <div class="text-xs text-gray-900">{{ $a->resultado_ultimo ?? '—' }}</div>
                                        <div class="text-xs text-gray-500">{{ \Illuminate\Support\Carbon::parse($a->fecha_ultima_gestion)->diffForHumans() }}</div>
                                    @else
                                        <span class="text-xs text-gray-400">sin gestiones</span>
                                    @endif
                                    @if($a->tiene_promesa_vigente)
                                        <div class="text-[10px] uppercase text-emerald-700 font-semibold mt-0.5">promesa vigente</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @php
                                        $estadoColor = match ($a->estado) {
                                            'pendiente' => 'bg-gray-100 text-gray-700',
                                            'en_trabajo' => 'bg-blue-100 text-blue-800',
                                            'cerrada' => 'bg-emerald-100 text-emerald-800',
                                            default => 'bg-gray-100 text-gray-700',
                                        };
                                    @endphp
                                    <span class="inline-block rounded px-2 py-0.5 text-xs font-medium {{ $estadoColor }}">
                                        {{ ucfirst(str_replace('_', ' ', $a->estado)) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <a href="{{ route('trabajo', ['cliente' => $a->cliente_public_id, 'producto' => $a->producto_public_id]) }}"
                                           wire:navigate
                                           class="inline-flex items-center px-3 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded-md hover:bg-indigo-700">
                                            Trabajar
                                        </a>
                                        @if($a->estado !== 'cerrada')
                                            <button type="button"
                                                    wire:click="cerrarAsignacion({{ $a->id }})"
                                                    wire:confirm="¿Cerrar esta asignación? La acción es reversible solo reabriéndola manualmente."
                                                    class="inline-flex items-center px-2 py-1.5 border border-gray-300 bg-white text-gray-700 text-xs font-medium rounded-md hover:bg-gray-50"
                                                    title="Cerrar asignación">
                                                Cerrar
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="px-4 py-3 border-t border-gray-200 bg-gray-50">
                {{ $asignaciones->links() }}
            </div>
        @endif
    </section>
</div>
