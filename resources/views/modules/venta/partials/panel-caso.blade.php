@php
    /** @var object|null $lead */
    $fmt = fn ($monto) => numero_local($monto);
@endphp

@if($lead)
    <div class="flex items-start justify-between gap-4 flex-wrap">
        <div class="min-w-0">
            <div class="label-xs">{{ __('venta.panel_label') }}</div>
            <div class="font-mono text-4xl font-semibold text-ink mt-0.5 truncate">{{ $lead->codigo_lead }}</div>
        </div>
        <div class="text-right">
            <div class="label-xs">{{ __('venta.valor_estimado') }}</div>
            <div class="font-mono text-5xl font-semibold text-ink mt-0.5 tracking-tight">{{ $lead->moneda }} {{ $fmt($lead->valor_estimado) }}</div>
        </div>
    </div>

    <dl class="mt-4 grid grid-cols-[repeat(auto-fill,minmax(150px,1fr))] gap-y-3.5 gap-x-5">
        <div>
            <dt class="text-xs text-ink-500">{{ __('venta.producto') }}</dt>
            <dd class="text-md font-medium text-ink mt-0.5">{{ $lead->producto_nombre ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-xs text-ink-500">{{ __('venta.etapa_embudo') }}</dt>
            <dd class="text-md font-medium text-ink mt-0.5">
                {{ $lead->etapa_nombre ?? '—' }}
                @if($lead->etapa_probabilidad !== null)
                    <span class="text-xs font-normal text-ink-500">· {{ $lead->etapa_probabilidad }}%</span>
                @endif
            </dd>
        </div>
        <div>
            <dt class="text-xs text-ink-500">{{ __('venta.origen') }}</dt>
            <dd class="text-md font-medium text-ink mt-0.5">{{ $lead->origen_lead ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-xs text-ink-500">{{ __('venta.primer_contacto') }}</dt>
            <dd class="font-mono text-md font-medium text-ink mt-0.5">{{ fecha_local($lead->fecha_primer_contacto) }}</dd>
        </div>
        @if($lead->fecha_estimada_cierre)
            <div class="col-span-full">
                <dt class="text-xs text-ink-500">{{ __('venta.cierre_estimado') }}</dt>
                <dd class="font-mono text-md font-semibold text-ink mt-0.5">
                    {{ fecha_local($lead->fecha_estimada_cierre) }}
                    <span class="font-sans text-xs font-normal text-ink-500">· {{ hace_cuanto($lead->fecha_estimada_cierre) }}</span>
                </dd>
            </div>
        @endif
    </dl>
@endif
