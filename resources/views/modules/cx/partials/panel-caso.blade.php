@php
    /** @var object|null $ticket */
@endphp

@if($ticket)
    <div class="rounded-md border border-sky-200 bg-sky-50 p-4 space-y-3">
        <div class="flex items-start justify-between gap-4">
            <div>
                <div class="text-xs uppercase tracking-wider text-sky-800 font-semibold">Ticket</div>
                <div class="text-lg font-semibold text-sky-900 font-mono">{{ $ticket->codigo_ticket }}</div>
                <div class="text-sm text-sky-900 mt-0.5">{{ $ticket->asunto }}</div>
            </div>
            @if($ticket->prioridad_nombre)
                @php
                    $badge = match (strtoupper((string) $ticket->prioridad_codigo)) {
                        'URGENTE' => 'bg-red-100 text-red-800',
                        'ALTA'    => 'bg-orange-100 text-orange-800',
                        'MEDIA'   => 'bg-amber-100 text-amber-800',
                        default   => 'bg-emerald-100 text-emerald-800',
                    };
                @endphp
                <span class="inline-block rounded px-2 py-1 text-xs font-medium {{ $badge }}">
                    {{ $ticket->prioridad_nombre }}
                </span>
            @endif
        </div>

        @if($ticket->descripcion)
            <div class="text-xs text-sky-900/80 whitespace-pre-line">{{ $ticket->descripcion }}</div>
        @endif

        <dl class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
            <div>
                <dt class="text-sky-700">Categoría</dt>
                <dd class="font-medium text-sky-900">{{ $ticket->categoria_nombre ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-sky-700">SLA</dt>
                <dd class="font-medium text-sky-900">{{ $ticket->sla_nombre ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-sky-700">Escalamiento</dt>
                <dd class="font-medium text-sky-900">{{ $ticket->escalamiento_nombre ?? 'N1' }}</dd>
            </div>
            <div>
                <dt class="text-sky-700">Reportado</dt>
                <dd class="font-medium text-sky-900">
                    {{ \Illuminate\Support\Carbon::parse($ticket->fecha_reporte)->format('d/m/Y H:i') }}
                </dd>
            </div>
            @if($ticket->fecha_limite_sla)
                <div class="col-span-2 sm:col-span-4">
                    <dt class="text-sky-700">Límite SLA</dt>
                    <dd class="font-semibold text-sky-900">
                        {{ \Illuminate\Support\Carbon::parse($ticket->fecha_limite_sla)->format('d/m/Y H:i') }}
                        @php $diff = \Illuminate\Support\Carbon::parse($ticket->fecha_limite_sla)->diffForHumans(); @endphp
                        <span class="text-[10px] text-sky-700">· {{ $diff }}</span>
                    </dd>
                </div>
            @endif
        </dl>
    </div>
@endif
