@php
    $labelIntentadas = match ($proyecto->tipo_operacion ?? '') {
        'cx' => __('reportes.label_intentadas_cx'),
        'venta' => __('reportes.label_intentadas_venta'),
        'servicio' => __('reportes.label_intentadas_servicio'),
        default => __('reportes.label_intentadas_cobranza'),
    };
    $labelGestionadas = match ($proyecto->tipo_operacion ?? '') {
        'cx' => __('reportes.label_gestionadas_cx'),
        'venta' => __('reportes.label_gestionadas_venta'),
        'servicio' => __('reportes.label_gestionadas_servicio'),
        default => __('reportes.label_gestionadas_cobranza'),
    };
    $rangos = [
        'hoy'    => __('reportes.range_today'),
        'ayer'   => __('reportes.range_yesterday'),
        'semana' => __('reportes.range_week'),
        'mes'    => __('reportes.range_month'),
    ];
@endphp
<div class="space-y-4">
    <div class="flex items-center justify-between gap-3">
        <div>
            <h3 class="text-sm font-semibold uppercase tracking-wider text-ink-700">
                {{ __('reportes.dashboard_operativo_title', ['rango' => $etiquetaRango]) }}
            </h3>
            <div class="text-xs text-ink-500">{{ $proyecto->nombre }} · {{ $proyecto->codigo }}</div>
        </div>
        <div class="flex items-center gap-2 text-xs">
            @foreach($rangos as $valor => $label)
                <button type="button" wire:click="$set('rango', '{{ $valor }}')"
                        class="px-3 py-1.5 rounded border {{ $rango === $valor ? 'bg-brand-600 text-white border-brand-600' : 'bg-white text-ink-700 border-ink-300 hover:bg-ink-50' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <div class="rounded-lg border border-ink-200 bg-white p-4">
            <div class="text-[10px] uppercase tracking-wider text-ink-500">{{ $labelIntentadas }}</div>
            <div class="text-2xl font-semibold text-ink-900 mt-1">{{ number_format($cuentasIntentadas) }}</div>
        </div>
        <div class="rounded-lg border border-ink-200 bg-white p-4">
            <div class="text-[10px] uppercase tracking-wider text-ink-500">{{ $labelGestionadas }}</div>
            <div class="text-2xl font-semibold text-success-700 mt-1">{{ number_format($cuentasGestionadas) }}</div>
        </div>
        <div class="rounded-lg border border-ink-200 bg-white p-4">
            <div class="text-[10px] uppercase tracking-wider text-ink-500">{{ __('reportes.kpi_effectiveness') }}</div>
            <div class="text-2xl font-semibold text-brand-700 mt-1">{{ number_format($efectividad, 1) }}%</div>
        </div>
        <div class="rounded-lg border border-ink-200 bg-white p-4">
            <div class="text-[10px] uppercase tracking-wider text-ink-500">{{ __('reportes.kpi_total_gestiones') }}</div>
            <div class="text-2xl font-semibold text-ink-900 mt-1">{{ number_format($totalGestiones) }}</div>
        </div>
        <div class="rounded-lg border border-success-200 bg-success-50 p-4">
            <div class="text-[10px] uppercase tracking-wider text-success-700">{{ __('reportes.kpi_commitments_active') }}</div>
            <div class="text-2xl font-semibold text-success-700 mt-1">{{ number_format($compromisosVigentes) }}</div>
        </div>
        <div class="rounded-lg border border-danger-200 bg-danger-50 p-4">
            <div class="text-[10px] uppercase tracking-wider text-danger-700">{{ __('reportes.kpi_commitments_overdue') }}</div>
            <div class="text-2xl font-semibold text-danger-700 mt-1">{{ number_format($compromisosVencidos) }}</div>
        </div>
    </div>

    <section class="rounded-lg border border-ink-200 bg-white overflow-hidden">
        <div class="px-4 py-3 border-b border-ink-200 bg-ink-50 text-xs font-semibold uppercase tracking-wider text-ink-600">
            {{ __('reportes.section_ranking', ['count' => $ranking->count()]) }}
        </div>
        @if($ranking->isEmpty())
            <div class="p-6 text-sm text-ink-500 text-center">{{ __('reportes.empty_ranking') }}</div>
        @else
            <table class="min-w-full divide-y divide-ink-200 text-sm">
                <thead class="bg-ink-50 text-xs uppercase tracking-wider text-ink-600">
                    <tr>
                        <th class="px-3 py-2 text-left">{{ __('reportes.col_agent') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('reportes.col_gestiones') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('reportes.col_attempted') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('reportes.col_managed') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('reportes.col_effectiveness') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    @foreach($ranking as $r)
                        @php
                            $ef = $r->cuentas_intentadas > 0 ? round(($r->cuentas_gestionadas / $r->cuentas_intentadas) * 100, 1) : 0.0;
                        @endphp
                        <tr>
                            <td class="px-3 py-2">{{ $r->name }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format($r->total_gestiones) }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format($r->cuentas_intentadas) }}</td>
                            <td class="px-3 py-2 text-right font-mono text-success-700">{{ number_format($r->cuentas_gestionadas) }}</td>
                            <td class="px-3 py-2 text-right font-mono text-brand-700">{{ number_format($ef, 1) }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <section class="rounded-lg border border-ink-200 bg-white overflow-hidden">
        <div class="px-4 py-3 border-b border-ink-200 bg-ink-50 text-xs font-semibold uppercase tracking-wider text-ink-600">
            {{ __('reportes.section_recent', ['count' => $gestiones->count()]) }}
        </div>
        @if($gestiones->isEmpty())
            <div class="p-6 text-sm text-ink-500 text-center">{{ __('reportes.empty_gestiones') }}</div>
        @else
            <table class="min-w-full divide-y divide-ink-200 text-sm">
                <thead class="bg-ink-50 text-xs uppercase tracking-wider text-ink-600">
                    <tr>
                        <th class="px-3 py-2 text-left">{{ __('reportes.col_date') }}</th>
                        <th class="px-3 py-2 text-left">{{ __('reportes.col_person') }}</th>
                        <th class="px-3 py-2 text-left">{{ __('reportes.col_case_type') }}</th>
                        <th class="px-3 py-2 text-left">{{ __('reportes.col_result_col') }}</th>
                        <th class="px-3 py-2 text-left">{{ __('reportes.col_channel') }}</th>
                        <th class="px-3 py-2 text-left">{{ __('reportes.col_user') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    @foreach($gestiones as $g)
                        @php
                            $nombre = $g->tipo_persona === 'juridica'
                                ? (string) ($g->razon_social ?? '')
                                : trim((string) ($g->nombres ?? '').' '.(string) ($g->apellidos ?? ''));
                        @endphp
                        <tr>
                            <td class="px-3 py-2 text-xs">{{ \Illuminate\Support\Carbon::parse($g->creada_en)->format('d/m H:i') }}</td>
                            <td class="px-3 py-2">
                                <div class="text-ink-900">{{ $nombre !== '' ? $nombre : '—' }}</div>
                                <div class="text-[10px] text-ink-500 font-mono">{{ $g->identificacion }}</div>
                            </td>
                            <td class="px-3 py-2 text-xs">{{ ucfirst(str_replace('_', ' ', (string) $g->tipo_caso)) }}</td>
                            <td class="px-3 py-2 text-xs">
                                {{ $g->resultado_nombre ?? '—' }}
                                @if($g->es_contacto_efectivo)
                                    <span class="inline-block rounded px-1.5 py-0.5 text-[10px] bg-success-50 text-success-800">{{ __('reportes.effective_badge') }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-xs">{{ $g->canal ?? '—' }}</td>
                            <td class="px-3 py-2 text-xs">{{ $g->usuario ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>
</div>
