<div class="space-y-4">
    <section class="rounded-lg border border-ink-200 bg-white p-4 flex items-center justify-between">
        <div>
            <h3 class="text-sm font-semibold uppercase tracking-wider text-ink-700">{{ __('reportes.teams_metrics_title') }}</h3>
            <p class="text-xs text-ink-500 mt-1">{{ __('reportes.teams_metrics_subtitle', ['rango' => $etiquetaRango, 'proyecto' => $proyecto->nombre]) }}</p>
        </div>
        <div class="flex items-center gap-2 text-sm">
            <label class="text-xs text-ink-600">{{ __('reportes.range_label') }}</label>
            <select wire:model.live="rango" class="border-ink-300 rounded-md text-sm">
                <option value="hoy">{{ __('reportes.range_today') }}</option>
                <option value="ayer">{{ __('reportes.range_yesterday') }}</option>
                <option value="semana">{{ __('reportes.range_last7') }}</option>
                <option value="mes">{{ __('reportes.range_current_month') }}</option>
            </select>
        </div>
    </section>

    <section class="rounded-lg border border-ink-200 bg-white overflow-hidden">
        @if(empty($filas))
            <div class="p-6 text-sm text-ink-500 text-center">{{ __('reportes.empty_teams') }}</div>
        @else
            <table class="min-w-full divide-y divide-ink-200 text-sm">
                <thead class="bg-ink-50 text-xs uppercase tracking-wider text-ink-600">
                    <tr>
                        <th class="px-3 py-2 text-left">{{ __('reportes.col_team') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('reportes.col_members') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('reportes.col_gestiones') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('reportes.col_attempted_cases') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('reportes.col_managed_cases') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('reportes.col_effectiveness') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('reportes.col_active_commitments') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('reportes.col_overdue_commitments') }}</th>
                        <th class="px-3 py-2 text-center">{{ __('reportes.col_detail') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    @foreach($filas as $f)
                        @php $eq = $f['equipo']; $expandido = $detalle !== null && $equipoExpandidoId === (int) $eq->id; @endphp
                        <tr class="{{ $expandido ? 'bg-brand-50/50' : '' }}">
                            <td class="px-3 py-2">
                                <div class="font-medium text-ink-900">{{ $eq->nombre }}</div>
                                <div class="text-[10px] text-ink-500 font-mono">{{ $eq->codigo }}</div>
                            </td>
                            <td class="px-3 py-2 text-right font-mono">{{ $f['miembros_count'] }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format($f['total_gestiones']) }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format($f['cuentas_intentadas']) }}</td>
                            <td class="px-3 py-2 text-right font-mono text-success-700">{{ number_format($f['cuentas_gestionadas']) }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format($f['efectividad'], 1) }}%</td>
                            <td class="px-3 py-2 text-right font-mono text-warning-700">{{ number_format($f['compromisos_vigentes']) }}</td>
                            <td class="px-3 py-2 text-right font-mono text-danger-700">{{ number_format($f['compromisos_vencidos']) }}</td>
                            <td class="px-3 py-2 text-center">
                                <button type="button" wire:click="expandir({{ $eq->id }})"
                                        class="text-xs text-brand-700 hover:underline">
                                    {{ $expandido ? __('reportes.btn_hide') : __('reportes.btn_members') }}
                                </button>
                            </td>
                        </tr>
                        @if($expandido && $detalle !== null)
                            <tr class="bg-brand-50/30">
                                <td colspan="9" class="px-6 py-3">
                                    @if(empty($detalle))
                                        <div class="text-xs text-ink-500">{{ __('reportes.empty_team_members') }}</div>
                                    @else
                                        <table class="min-w-full text-xs">
                                            <thead class="text-ink-600">
                                                <tr>
                                                    <th class="px-2 py-1 text-left">{{ __('reportes.col_member') }}</th>
                                                    <th class="px-2 py-1 text-left">{{ __('reportes.col_email') }}</th>
                                                    <th class="px-2 py-1 text-right">{{ __('reportes.col_gestiones') }}</th>
                                                    <th class="px-2 py-1 text-right">{{ __('reportes.col_attempted_m') }}</th>
                                                    <th class="px-2 py-1 text-right">{{ __('reportes.col_managed_m') }}</th>
                                                    <th class="px-2 py-1 text-right">{{ __('reportes.col_effectiveness') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($detalle as $m)
                                                    <tr>
                                                        <td class="px-2 py-1">{{ $m['nombre'] }}</td>
                                                        <td class="px-2 py-1 text-ink-500">{{ $m['email'] }}</td>
                                                        <td class="px-2 py-1 text-right font-mono">{{ number_format($m['total']) }}</td>
                                                        <td class="px-2 py-1 text-right font-mono">{{ number_format($m['intentadas']) }}</td>
                                                        <td class="px-2 py-1 text-right font-mono text-success-700">{{ number_format($m['gestionadas']) }}</td>
                                                        <td class="px-2 py-1 text-right font-mono">{{ number_format($m['efectividad'], 1) }}%</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    @endif
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>
</div>
