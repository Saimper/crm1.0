<div class="space-y-4">
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-sm font-semibold uppercase tracking-wider text-ink-700">{{ __('reportes.dashboard_analitico_title') }}</h3>
            <div class="text-xs text-ink-500">{{ $proyecto->nombre }} · {{ $proyecto->codigo }}</div>
        </div>
    </div>

    <section class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="rounded-lg border border-ink-200 bg-white overflow-hidden">
            <div class="px-4 py-3 border-b border-ink-200 bg-ink-50 text-xs font-semibold uppercase tracking-wider text-ink-600">
                {{ __('reportes.chart_cases_by_type') }}
            </div>
            @if($distribucionCasos->isEmpty())
                <div class="p-4 text-sm text-ink-500">{{ __('reportes.empty_cases') }}</div>
            @else
                <table class="min-w-full text-sm">
                    <tbody class="divide-y divide-ink-100">
                        @foreach($distribucionCasos as $d)
                            @php
                                $color = match ($d->tipo_caso) {
                                    'cobranza'   => 'bg-warning-50 text-warning-700',
                                    'ticket_cx'  => 'bg-sky-100 text-sky-800',
                                    'lead_venta' => 'bg-success-50 text-success-800',
                                    'servicio'   => 'bg-brand-100 text-brand-800',
                                    default      => 'bg-ink-100 text-ink-700',
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

        <div class="rounded-lg border border-ink-200 bg-white overflow-hidden">
            <div class="px-4 py-3 border-b border-ink-200 bg-ink-50 text-xs font-semibold uppercase tracking-wider text-ink-600">
                {{ __('reportes.chart_commitments_by_state') }}
            </div>
            @if($compromisosPorEstado->isEmpty())
                <div class="p-4 text-sm text-ink-500">{{ __('reportes.empty_commitments') }}</div>
            @else
                <table class="min-w-full text-sm">
                    <thead class="bg-ink-50 text-xs uppercase tracking-wider text-ink-600">
                        <tr>
                            <th class="px-3 py-2 text-left">{{ __('reportes.col_type') }}</th>
                            <th class="px-3 py-2 text-left">{{ __('reportes.col_state') }}</th>
                            <th class="px-3 py-2 text-right">{{ __('reportes.col_total') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100">
                        @foreach($compromisosPorEstado as $c)
                            @php
                                $badge = match ($c->estado) {
                                    'pendiente' => 'bg-warning-50 text-warning-700',
                                    'cumplido'  => 'bg-success-50 text-success-800',
                                    'roto'      => 'bg-danger-50 text-danger-700',
                                    'cancelado' => 'bg-ink-100 text-ink-700',
                                    default     => 'bg-ink-100 text-ink-700',
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

    <section class="rounded-lg border border-ink-200 bg-white overflow-hidden">
        <div class="px-4 py-3 border-b border-ink-200 bg-ink-50 text-xs font-semibold uppercase tracking-wider text-ink-600">
            {{ __('reportes.chart_gestiones_by_month') }}
        </div>
        @if($gestionesPorMes->isEmpty())
            <div class="p-4 text-sm text-ink-500">{{ __('reportes.empty_activity') }}</div>
        @else
            <div class="p-4 space-y-2">
                @php $max = max($gestionesPorMes->pluck('total')->toArray() ?: [1]); @endphp
                @foreach($gestionesPorMes as $m)
                    @php $porcent = $max > 0 ? (int) round($m->total / $max * 100) : 0; @endphp
                    <div class="flex items-center gap-3 text-xs">
                        <div class="w-16 font-mono text-ink-600">{{ $m->mes }}</div>
                        <div class="flex-1 bg-ink-100 rounded h-4 overflow-hidden">
                            <div class="bg-brand-600 h-4" style="width: {{ $porcent }}%"></div>
                        </div>
                        <div class="w-16 text-right font-mono">{{ number_format($m->total) }}</div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    <section class="rounded-lg border border-ink-200 bg-white overflow-hidden">
        <div class="px-4 py-3 border-b border-ink-200 bg-ink-50 text-xs font-semibold uppercase tracking-wider text-ink-600">
            {{ __('reportes.chart_effectiveness', ['total' => number_format($totalGestiones)]) }}
        </div>
        @if($efectividadPorResultado->isEmpty())
            <div class="p-4 text-sm text-ink-500">{{ __('reportes.empty_data') }}</div>
        @else
            <table class="min-w-full divide-y divide-ink-200 text-sm">
                <thead class="bg-ink-50 text-xs uppercase tracking-wider text-ink-600">
                    <tr>
                        <th class="px-3 py-2 text-left">{{ __('reportes.col_result') }}</th>
                        <th class="px-3 py-2 text-left">{{ __('reportes.col_effective') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('reportes.col_total') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('reportes.col_percent') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    @foreach($efectividadPorResultado as $r)
                        @php $pct = $totalGestiones > 0 ? round($r->total / $totalGestiones * 100, 1) : 0.0; @endphp
                        <tr>
                            <td class="px-3 py-2">{{ $r->nombre }} <span class="text-[10px] text-ink-500 font-mono">({{ $r->codigo }})</span></td>
                            <td class="px-3 py-2 text-xs">
                                @if($r->es_contacto_efectivo)
                                    <span class="inline-block rounded px-1.5 py-0.5 text-[10px] bg-success-50 text-success-800">{{ __('reportes.yes') }}</span>
                                @else
                                    <span class="inline-block rounded px-1.5 py-0.5 text-[10px] bg-ink-100 text-ink-700">{{ __('reportes.no') }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format($r->total) }}</td>
                            <td class="px-3 py-2 text-right font-mono text-brand-700">{{ $pct }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <section class="rounded-lg border border-ink-200 bg-white overflow-hidden">
        <div class="px-4 py-3 border-b border-ink-200 bg-ink-50 text-xs font-semibold uppercase tracking-wider text-ink-600">
            {{ __('reportes.chart_top_days') }}
        </div>
        @if($topDias->isEmpty())
            <div class="p-4 text-sm text-ink-500">{{ __('reportes.empty_data') }}</div>
        @else
            <table class="min-w-full text-sm">
                <tbody class="divide-y divide-ink-100">
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
