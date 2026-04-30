<div class="space-y-4">
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-700">Dashboard analítico</h3>
            <div class="text-xs text-gray-500">{{ $proyecto->nombre }} · {{ $proyecto->codigo }}</div>
        </div>
    </div>

    <section class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="rounded-lg border border-gray-200 bg-white overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-200 bg-gray-50 text-xs font-semibold uppercase tracking-wider text-gray-600">
                Distribución por tipo de caso
            </div>
            @if($distribucionCasos->isEmpty())
                <div class="p-4 text-sm text-gray-500">Sin casos.</div>
            @else
                <table class="min-w-full text-sm">
                    <tbody class="divide-y divide-gray-100">
                        @foreach($distribucionCasos as $d)
                            @php
                                $color = match ($d->tipo_caso) {
                                    'cobranza'   => 'bg-amber-100 text-amber-800',
                                    'ticket_cx'  => 'bg-sky-100 text-sky-800',
                                    'lead_venta' => 'bg-emerald-100 text-emerald-800',
                                    'servicio'   => 'bg-blue-100 text-blue-800',
                                    default      => 'bg-gray-100 text-gray-700',
                                };
                            @endphp
                            <tr>
                                <td class="px-4 py-2">
                                    <span class="inline-block rounded px-2 py-0.5 text-xs font-medium {{ $color }}">
                                        {{ ucfirst(str_replace('_', ' ', (string) $d->tipo_caso)) }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-right font-mono">{{ number_format($d->total) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="rounded-lg border border-gray-200 bg-white overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-200 bg-gray-50 text-xs font-semibold uppercase tracking-wider text-gray-600">
                Compromisos por tipo y estado
            </div>
            @if($compromisosPorEstado->isEmpty())
                <div class="p-4 text-sm text-gray-500">Sin compromisos.</div>
            @else
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-600">
                        <tr>
                            <th class="px-3 py-2 text-left">Tipo</th>
                            <th class="px-3 py-2 text-left">Estado</th>
                            <th class="px-3 py-2 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($compromisosPorEstado as $c)
                            @php
                                $badge = match ($c->estado) {
                                    'pendiente' => 'bg-amber-100 text-amber-800',
                                    'cumplido'  => 'bg-emerald-100 text-emerald-800',
                                    'roto'      => 'bg-red-100 text-red-800',
                                    'cancelado' => 'bg-gray-100 text-gray-700',
                                    default     => 'bg-gray-100 text-gray-700',
                                };
                            @endphp
                            <tr>
                                <td class="px-3 py-2 text-xs">{{ str_replace('_', ' ', (string) $c->tipo_compromiso) }}</td>
                                <td class="px-3 py-2">
                                    <span class="inline-block rounded px-2 py-0.5 text-xs {{ $badge }}">{{ $c->estado }}</span>
                                </td>
                                <td class="px-3 py-2 text-right font-mono">{{ number_format($c->total) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </section>

    <section class="rounded-lg border border-gray-200 bg-white overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200 bg-gray-50 text-xs font-semibold uppercase tracking-wider text-gray-600">
            Gestiones por mes (últimos 6 meses)
        </div>
        @if($gestionesPorMes->isEmpty())
            <div class="p-4 text-sm text-gray-500">Sin actividad.</div>
        @else
            <div class="p-4 space-y-2">
                @php $max = max($gestionesPorMes->pluck('total')->toArray() ?: [1]); @endphp
                @foreach($gestionesPorMes as $m)
                    @php $porcent = $max > 0 ? (int) round($m->total / $max * 100) : 0; @endphp
                    <div class="flex items-center gap-3 text-xs">
                        <div class="w-16 font-mono text-gray-600">{{ $m->mes }}</div>
                        <div class="flex-1 bg-gray-100 rounded h-4 overflow-hidden">
                            <div class="bg-blue-600 h-4" style="width: {{ $porcent }}%"></div>
                        </div>
                        <div class="w-16 text-right font-mono">{{ number_format($m->total) }}</div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    <section class="rounded-lg border border-gray-200 bg-white overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200 bg-gray-50 text-xs font-semibold uppercase tracking-wider text-gray-600">
            Efectividad por resultado · {{ number_format($totalGestiones) }} gestiones totales
        </div>
        @if($efectividadPorResultado->isEmpty())
            <div class="p-4 text-sm text-gray-500">Sin datos.</div>
        @else
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-600">
                    <tr>
                        <th class="px-3 py-2 text-left">Resultado</th>
                        <th class="px-3 py-2 text-left">Efectivo</th>
                        <th class="px-3 py-2 text-right">Total</th>
                        <th class="px-3 py-2 text-right">%</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($efectividadPorResultado as $r)
                        @php $pct = $totalGestiones > 0 ? round($r->total / $totalGestiones * 100, 1) : 0.0; @endphp
                        <tr>
                            <td class="px-3 py-2">{{ $r->nombre }} <span class="text-[10px] text-gray-500 font-mono">({{ $r->codigo }})</span></td>
                            <td class="px-3 py-2 text-xs">
                                @if($r->es_contacto_efectivo)
                                    <span class="inline-block rounded px-1.5 py-0.5 text-[10px] bg-emerald-100 text-emerald-800">sí</span>
                                @else
                                    <span class="inline-block rounded px-1.5 py-0.5 text-[10px] bg-gray-100 text-gray-700">no</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format($r->total) }}</td>
                            <td class="px-3 py-2 text-right font-mono text-blue-700">{{ $pct }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <section class="rounded-lg border border-gray-200 bg-white overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200 bg-gray-50 text-xs font-semibold uppercase tracking-wider text-gray-600">
            Top 5 días con más gestiones
        </div>
        @if($topDias->isEmpty())
            <div class="p-4 text-sm text-gray-500">Sin datos.</div>
        @else
            <table class="min-w-full text-sm">
                <tbody class="divide-y divide-gray-100">
                    @foreach($topDias as $d)
                        <tr>
                            <td class="px-4 py-2 font-mono text-xs">{{ \Illuminate\Support\Carbon::parse($d->dia)->format('d/m/Y') }}</td>
                            <td class="px-4 py-2 text-right font-mono">{{ number_format($d->total) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>
</div>
