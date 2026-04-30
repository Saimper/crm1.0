@php
    /** @var object|null $cobranza */
    $fmt = fn ($monto) => number_format((float) $monto, 2, '.', ',');
@endphp

@if($cobranza)
    <div class="rounded-md border border-amber-200 bg-amber-50 p-4 space-y-3">
        <div class="flex items-start justify-between gap-4">
            <div>
                <div class="text-[10px] uppercase tracking-wider text-amber-700">Caso de cobranza · Préstamo</div>
                <div class="text-lg font-semibold text-amber-900 font-mono">{{ $cobranza->numero_prestamo }}</div>
            </div>
            <div class="text-right">
                <div class="text-xs uppercase tracking-wider text-amber-800 font-semibold">Saldo total</div>
                <div class="text-lg font-semibold text-amber-900">
                    {{ $cobranza->moneda }} {{ $fmt($cobranza->saldo_total) }}
                </div>
            </div>
        </div>

        <dl class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
            <div>
                <dt class="text-amber-700">Saldo capital</dt>
                <dd class="font-medium text-amber-900">{{ $cobranza->moneda }} {{ $fmt($cobranza->saldo_capital) }}</dd>
            </div>
            <div>
                <dt class="text-amber-700">Saldo interés</dt>
                <dd class="font-medium text-amber-900">{{ $cobranza->moneda }} {{ $fmt($cobranza->saldo_interes) }}</dd>
            </div>
            <div>
                <dt class="text-amber-700">Cuota mensual</dt>
                <dd class="font-medium text-amber-900">{{ $cobranza->moneda }} {{ $fmt($cobranza->cuota_mensual) }}</dd>
            </div>
            <div>
                <dt class="text-amber-700">Cuotas</dt>
                <dd class="font-medium text-amber-900">{{ $cobranza->cuotas_pagadas }}/{{ $cobranza->cuotas_totales }}</dd>
            </div>
            <div>
                <dt class="text-amber-700">Días mora</dt>
                <dd class="font-semibold {{ $cobranza->dias_mora > 0 ? 'text-red-700' : 'text-emerald-700' }}">
                    {{ $cobranza->dias_mora }}
                    @if($cobranza->tramo_mora_nombre)
                        <span class="text-[10px] text-amber-700">· {{ $cobranza->tramo_mora_nombre }}</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-amber-700">Monto original</dt>
                <dd class="font-medium text-amber-900">{{ $cobranza->moneda }} {{ $fmt($cobranza->monto_original) }}</dd>
            </div>
            <div>
                <dt class="text-amber-700">Desembolso</dt>
                <dd class="font-medium text-amber-900">
                    {{ \Illuminate\Support\Carbon::parse($cobranza->fecha_desembolso)->format('d/m/Y') }}
                </dd>
            </div>
            <div>
                <dt class="text-amber-700">Vencimiento</dt>
                <dd class="font-medium text-amber-900">
                    {{ \Illuminate\Support\Carbon::parse($cobranza->fecha_vencimiento)->format('d/m/Y') }}
                </dd>
            </div>
        </dl>
    </div>
@endif
