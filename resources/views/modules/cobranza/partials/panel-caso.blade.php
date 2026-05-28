@php
    /** @var object|null $cobranza */
    // F34C P3-1: paleta amber dedicada al panel cobranza. Decisión consciente:
    // los 4 paneles tipo-específicos usan colores distintos (amber/sky/emerald/blue)
    // para discriminación visual rápida. F29-bis cerrará con tokens dedicados
    // (--panel-cobranza-bg, etc.) cuando el design system se finalice.
    $fmt = fn ($monto) => number_format((float) $monto, 2, '.', ',');
@endphp

@if($cobranza)
    <div class="rounded-md border border-warning-200 bg-warning-50 p-4 space-y-3">
        <div class="flex items-start justify-between gap-4">
            <div>
                <div class="text-[10px] uppercase tracking-wider text-warning-700">{{ __('cobranza.panel_label') }}</div>
                <div class="text-lg font-semibold text-warning-700 font-mono">{{ $cobranza->numero_prestamo }}</div>
            </div>
            <div class="text-right">
                <div class="text-xs uppercase tracking-wider text-warning-700 font-semibold">{{ __('cobranza.saldo_total') }}</div>
                <div class="text-lg font-semibold text-warning-700">
                    {{ $cobranza->moneda }} {{ $fmt($cobranza->saldo_total) }}
                </div>
            </div>
        </div>

        <dl class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
            <div>
                <dt class="text-warning-700">{{ __('cobranza.saldo_capital') }}</dt>
                <dd class="font-medium text-warning-700">{{ $cobranza->moneda }} {{ $fmt($cobranza->saldo_capital) }}</dd>
            </div>
            <div>
                <dt class="text-warning-700">{{ __('cobranza.saldo_interes') }}</dt>
                <dd class="font-medium text-warning-700">{{ $cobranza->moneda }} {{ $fmt($cobranza->saldo_interes) }}</dd>
            </div>
            <div>
                <dt class="text-warning-700">{{ __('cobranza.cuota_mensual') }}</dt>
                <dd class="font-medium text-warning-700">{{ $cobranza->moneda }} {{ $fmt($cobranza->cuota_mensual) }}</dd>
            </div>
            <div>
                <dt class="text-warning-700">{{ __('cobranza.cuotas') }}</dt>
                <dd class="font-medium text-warning-700">{{ $cobranza->cuotas_pagadas }}/{{ $cobranza->cuotas_totales }}</dd>
            </div>
            <div>
                <dt class="text-warning-700">{{ __('cobranza.dias_mora') }}</dt>
                <dd class="font-semibold {{ $cobranza->dias_mora > 0 ? 'text-danger-700' : 'text-success-700' }}">
                    {{ $cobranza->dias_mora }}
                    @if($cobranza->tramo_mora_nombre)
                        <span class="text-[10px] text-warning-700">· {{ $cobranza->tramo_mora_nombre }}</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-warning-700">{{ __('cobranza.monto_original') }}</dt>
                <dd class="font-medium text-warning-700">{{ $cobranza->moneda }} {{ $fmt($cobranza->monto_original) }}</dd>
            </div>
            <div>
                <dt class="text-warning-700">{{ __('cobranza.desembolso') }}</dt>
                <dd class="font-medium text-warning-700">
                    {{ \Illuminate\Support\Carbon::parse($cobranza->fecha_desembolso)->format('d/m/Y') }}
                </dd>
            </div>
            <div>
                <dt class="text-warning-700">{{ __('cobranza.vencimiento') }}</dt>
                <dd class="font-medium text-warning-700">
                    {{ \Illuminate\Support\Carbon::parse($cobranza->fecha_vencimiento)->format('d/m/Y') }}
                </dd>
            </div>
        </dl>
    </div>
@endif
