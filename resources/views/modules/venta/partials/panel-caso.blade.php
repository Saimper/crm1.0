@php
    /** @var object|null $lead */
    $fmt = fn ($monto) => number_format((float) $monto, 2, '.', ',');
@endphp

@if($lead)
    <div class="card card-pad" style="space-y:12px;">
        <div class="flex items-start justify-between gap-4">
            <div>
                <div class="text-[10px] uppercase tracking-wider" style="color:var(--text-tertiary);">Caso de venta · Lead</div>
                <div class="text-lg font-semibold font-mono" style="color:var(--text);">{{ $lead->codigo_lead }}</div>
            </div>
            <div class="text-right">
                <div class="text-xs uppercase tracking-wider font-semibold" style="color:var(--text-secondary);">Valor estimado</div>
                <div class="text-lg font-semibold" style="color:var(--text);">
                    {{ $lead->moneda }} {{ $fmt($lead->valor_estimado) }}
                </div>
            </div>
        </div>

        <dl class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs" style="margin-top:12px;">
            <div>
                <dt style="color:var(--text-tertiary);">Producto</dt>
                <dd class="font-medium" style="color:var(--text);">{{ $lead->producto_nombre ?? '—' }}</dd>
            </div>
            <div>
                <dt style="color:var(--text-tertiary);">Etapa embudo</dt>
                <dd class="font-medium" style="color:var(--text);">
                    {{ $lead->etapa_nombre ?? '—' }}
                    @if($lead->etapa_probabilidad !== null)
                        <span class="text-[10px]" style="color:var(--text-tertiary);">· {{ $lead->etapa_probabilidad }}%</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt style="color:var(--text-tertiary);">Origen</dt>
                <dd class="font-medium" style="color:var(--text);">{{ $lead->origen_lead ?? '—' }}</dd>
            </div>
            <div>
                <dt style="color:var(--text-tertiary);">Primer contacto</dt>
                <dd class="font-medium" style="color:var(--text);">
                    {{ \Illuminate\Support\Carbon::parse($lead->fecha_primer_contacto)->format('d/m/Y') }}
                </dd>
            </div>
            @if($lead->fecha_estimada_cierre)
                <div class="col-span-2 sm:col-span-4">
                    <dt style="color:var(--text-tertiary);">Cierre estimado</dt>
                    <dd class="font-semibold" style="color:var(--text);">
                        {{ \Illuminate\Support\Carbon::parse($lead->fecha_estimada_cierre)->format('d/m/Y') }}
                        @php $diff = \Illuminate\Support\Carbon::parse($lead->fecha_estimada_cierre)->diffForHumans(); @endphp
                        <span class="text-[10px]" style="color:var(--text-tertiary);">· {{ $diff }}</span>
                    </dd>
                </div>
            @endif
        </dl>
    </div>
@endif
