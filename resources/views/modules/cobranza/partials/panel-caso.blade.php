@php
    /** @var object|null $cobranza */
    // La ficha completa de la cuenta, en lectura. Un solo aspecto para los cuatro
    // tipos de proyecto: el color se reserva para lo que tiene estado (la mora).
    $fmt = fn ($monto) => numero_local($monto);
@endphp

@if($cobranza)
    <div class="flex items-start justify-between gap-4 flex-wrap">
        <div class="min-w-0">
            <div class="label-xs">{{ __('cobranza.panel_label') }}</div>
            <div class="font-mono text-4xl font-semibold text-ink mt-0.5 truncate">{{ $cobranza->numero_prestamo }}</div>
        </div>
        <div class="text-right">
            <div class="label-xs">{{ __('cobranza.saldo_total') }}</div>
            <div class="font-mono text-5xl font-semibold text-ink mt-0.5 tracking-tight">{{ $cobranza->moneda }} {{ $fmt($cobranza->saldo_total) }}</div>
        </div>
    </div>

    <dl class="mt-4 grid grid-cols-[repeat(auto-fill,minmax(150px,1fr))] gap-y-3.5 gap-x-5">
        <div>
            <dt class="text-xs text-ink-500">{{ __('cobranza.saldo_capital') }}</dt>
            <dd class="font-mono text-md font-medium text-ink mt-0.5">{{ $cobranza->moneda }} {{ $fmt($cobranza->saldo_capital) }}</dd>
        </div>
        <div>
            <dt class="text-xs text-ink-500">{{ __('cobranza.saldo_interes') }}</dt>
            <dd class="font-mono text-md font-medium text-ink mt-0.5">{{ $cobranza->moneda }} {{ $fmt($cobranza->saldo_interes) }}</dd>
        </div>
        <div>
            <dt class="text-xs text-ink-500">{{ __('cobranza.cuota_mensual') }}</dt>
            <dd class="font-mono text-md font-medium text-ink mt-0.5">{{ $cobranza->moneda }} {{ $fmt($cobranza->cuota_mensual) }}</dd>
        </div>
        <div>
            <dt class="text-xs text-ink-500">{{ __('cobranza.cuotas') }}</dt>
            <dd class="font-mono text-md font-medium text-ink mt-0.5">{{ $cobranza->cuotas_pagadas }}/{{ $cobranza->cuotas_totales }}</dd>
        </div>
        <div>
            <dt class="text-xs text-ink-500">{{ __('cobranza.dias_mora') }}</dt>
            <dd class="font-mono text-md font-semibold mt-0.5 {{ $cobranza->dias_mora > 0 ? 'text-danger-500' : 'text-success-600' }}">
                {{ $cobranza->dias_mora }}
                @if($cobranza->tramo_mora_nombre)
                    <span class="font-sans text-xs font-normal text-ink-500">· {{ $cobranza->tramo_mora_nombre }}</span>
                @endif
            </dd>
            {{-- El CRM envejece la mora cada día; esta línea dice cuándo la afirmó
                 una fuente por última vez (el archivo del cliente, un alta a mano,
                 un rescate), que es lo que distingue una mora confirmada de una
                 calculada por el reloj. No dice «el cliente» porque el dato no
                 guarda quién fue la fuente. --}}
            @if(!empty($cobranza->dias_mora_confirmado_en))
                <dd class="text-xs text-ink-500 mt-px">
                    {{ __('cobranza.dias_mora_confirmado_el', ['fecha' => fecha_local($cobranza->dias_mora_confirmado_en)]) }}
                </dd>
            @endif
        </div>
        <div>
            <dt class="text-xs text-ink-500">{{ __('cobranza.monto_original') }}</dt>
            <dd class="font-mono text-md font-medium text-ink mt-0.5">{{ $cobranza->moneda }} {{ $fmt($cobranza->monto_original) }}</dd>
        </div>
        <div>
            <dt class="text-xs text-ink-500">{{ __('cobranza.desembolso') }}</dt>
            <dd class="font-mono text-md font-medium text-ink mt-0.5">{{ fecha_local($cobranza->fecha_desembolso) }}</dd>
        </div>
        <div>
            <dt class="text-xs text-ink-500">{{ __('cobranza.vencimiento') }}</dt>
            <dd class="font-mono text-md font-medium text-ink mt-0.5">{{ fecha_local($cobranza->fecha_vencimiento) }}</dd>
        </div>
    </dl>
@endif
