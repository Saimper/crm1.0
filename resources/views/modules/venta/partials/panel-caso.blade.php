@php
    /** @var object|null $lead */
    $fmt = fn ($monto) => numero_local($monto);
@endphp

@if($lead)
    <div class="card card-pad" style="space-y:12px;">
        <div class="flex items-start justify-between gap-4">
            <div>
                <div class="text-[10px] uppercase tracking-wider text-ink-500">{{ __('venta.panel_label') }}</div>
                <div class="text-lg font-semibold font-mono text-ink">{{ $lead->codigo_lead }}</div>
            </div>
            <div class="text-right">
                <div class="text-xs uppercase tracking-wider font-semibold text-ink-600">{{ __('venta.valor_estimado') }}</div>
                <div class="text-lg font-semibold text-ink">
                    {{ $lead->moneda }} {{ $fmt($lead->valor_estimado) }}
                </div>
            </div>
        </div>

        <dl class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs" style="margin-top:12px;">
            <div>
                <dt class="text-ink-500">{{ __('venta.producto') }}</dt>
                <dd class="font-medium text-ink">{{ $lead->producto_nombre ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-ink-500">{{ __('venta.etapa_embudo') }}</dt>
                <dd class="font-medium text-ink">
                    {{ $lead->etapa_nombre ?? '—' }}
                    @if($lead->etapa_probabilidad !== null)
                        <span class="text-[10px] text-ink-500">· {{ $lead->etapa_probabilidad }}%</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-ink-500">{{ __('venta.origen') }}</dt>
                <dd class="font-medium text-ink">{{ $lead->origen_lead ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-ink-500">{{ __('venta.primer_contacto') }}</dt>
                <dd class="font-medium text-ink">
                    {{ fecha_local($lead->fecha_primer_contacto) }}
                </dd>
            </div>
            @if($lead->fecha_estimada_cierre)
                <div class="col-span-2 sm:col-span-4">
                    <dt class="text-ink-500">{{ __('venta.cierre_estimado') }}</dt>
                    <dd class="font-semibold text-ink">
                        {{ fecha_local($lead->fecha_estimada_cierre) }}
                        @php $diff = \Illuminate\Support\Carbon::parse($lead->fecha_estimada_cierre)->diffForHumans(); @endphp
                        <span class="text-[10px] text-ink-500">· {{ $diff }}</span>
                    </dd>
                </div>
            @endif
        </dl>
    </div>
@endif
