{{-- Filas del resumen de una cuenta de cobranza. Van dentro del <dl class="vt-dl">
     de la Vista de Trabajo, que añade las comunes (estado, asesor, cartera). --}}
@php /** @var object|null $cobranza */ @endphp

@if($cobranza)
    <dt>{{ __('cobranza.prestamo') }}</dt>
    <dd class="font-mono font-semibold text-ink">{{ $cobranza->numero_prestamo }}</dd>

    <dt>{{ __('cobranza.saldo_total') }}</dt>
    <dd class="font-mono text-3xl font-semibold tracking-tight text-ink">{{ $cobranza->moneda }} {{ numero_local($cobranza->saldo_total) }}</dd>

    <dt>{{ __('cobranza.dias_mora') }}</dt>
    <dd>
        <span class="font-semibold {{ $cobranza->dias_mora > 0 ? 'text-danger-500' : 'text-success-600' }}">{{ $cobranza->dias_mora }}</span>
        <span class="text-ink-500">
            @if($cobranza->tramo_mora_nombre) · {{ $cobranza->tramo_mora_nombre }} @endif
            @if(!empty($cobranza->dias_mora_confirmado_en)) · {{ __('cobranza.dias_mora_confirmado_el', ['fecha' => fecha_local($cobranza->dias_mora_confirmado_en)]) }} @endif
        </span>
    </dd>

    <dt>{{ __('cobranza.cuota_mensual') }}</dt>
    <dd>
        <span class="font-mono">{{ $cobranza->moneda }} {{ numero_local($cobranza->cuota_mensual) }}</span>
        <span class="text-ink-500">· {{ __('cobranza.cuota_de', ['pagadas' => $cobranza->cuotas_pagadas, 'totales' => $cobranza->cuotas_totales]) }}</span>
    </dd>
@endif
