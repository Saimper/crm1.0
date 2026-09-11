{{-- Filas del resumen de una oportunidad. Van dentro del <dl class="vt-dl"> de
     la Vista de Trabajo, que añade las comunes (estado, asesor, cartera). --}}
@php /** @var object|null $lead */ @endphp

@if($lead)
    <dt>{{ __('venta.lead') }}</dt>
    <dd class="font-mono font-semibold text-ink">{{ $lead->codigo_lead }}</dd>

    <dt>{{ __('venta.valor_estimado') }}</dt>
    <dd class="font-mono text-3xl font-semibold tracking-tight text-ink">{{ $lead->moneda }} {{ numero_local($lead->valor_estimado) }}</dd>

    <dt>{{ __('venta.etapa_embudo') }}</dt>
    <dd>
        {{ $lead->etapa_nombre ?? '—' }}
        @if($lead->etapa_probabilidad !== null)<span class="text-ink-500">· {{ $lead->etapa_probabilidad }}%</span>@endif
        @if($lead->producto_nombre)<span class="text-ink-500">· {{ $lead->producto_nombre }}</span>@endif
    </dd>

    @if($lead->fecha_estimada_cierre)
        <dt>{{ __('venta.cierre_estimado') }}</dt>
        <dd>
            <span class="font-mono font-semibold">{{ fecha_local($lead->fecha_estimada_cierre) }}</span>
            <span class="text-ink-500">· {{ hace_cuanto($lead->fecha_estimada_cierre) }}</span>
        </dd>
    @endif
@endif
