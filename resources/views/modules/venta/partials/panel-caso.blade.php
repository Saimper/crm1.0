@php
    /** @var object|null $lead */
    $fmt = fn ($monto) => number_format((float) $monto, 2, '.', ',');
@endphp

@if($lead)
    <div class="rounded-md border border-emerald-200 bg-emerald-50 p-4 space-y-3">
        <div class="flex items-start justify-between gap-4">
            <div>
                <div class="text-xs uppercase tracking-wider text-emerald-800 font-semibold">Lead</div>
                <div class="text-lg font-semibold text-emerald-900 font-mono">{{ $lead->codigo_lead }}</div>
            </div>
            <div class="text-right">
                <div class="text-xs uppercase tracking-wider text-emerald-800 font-semibold">Valor estimado</div>
                <div class="text-lg font-semibold text-emerald-900">
                    {{ $lead->moneda }} {{ $fmt($lead->valor_estimado) }}
                </div>
            </div>
        </div>

        <dl class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
            <div>
                <dt class="text-emerald-700">Producto</dt>
                <dd class="font-medium text-emerald-900">{{ $lead->producto_nombre ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-emerald-700">Etapa embudo</dt>
                <dd class="font-medium text-emerald-900">
                    {{ $lead->etapa_nombre ?? '—' }}
                    @if($lead->etapa_probabilidad !== null)
                        <span class="text-[10px] text-emerald-700">· {{ $lead->etapa_probabilidad }}%</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-emerald-700">Origen</dt>
                <dd class="font-medium text-emerald-900">{{ $lead->origen_lead ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-emerald-700">Primer contacto</dt>
                <dd class="font-medium text-emerald-900">
                    {{ \Illuminate\Support\Carbon::parse($lead->fecha_primer_contacto)->format('d/m/Y') }}
                </dd>
            </div>
            @if($lead->fecha_estimada_cierre)
                <div class="col-span-2 sm:col-span-4">
                    <dt class="text-emerald-700">Cierre estimado</dt>
                    <dd class="font-semibold text-emerald-900">
                        {{ \Illuminate\Support\Carbon::parse($lead->fecha_estimada_cierre)->format('d/m/Y') }}
                        @php $diff = \Illuminate\Support\Carbon::parse($lead->fecha_estimada_cierre)->diffForHumans(); @endphp
                        <span class="text-[10px] text-emerald-700">· {{ $diff }}</span>
                    </dd>
                </div>
            @endif
        </dl>
    </div>
@endif
