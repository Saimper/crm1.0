@php
    /** @var object|null $ticket */
    $tonoPrioridad = fn (?string $codigo): string => match (strtoupper((string) $codigo)) {
        'URGENTE' => 'danger',
        'ALTA', 'MEDIA' => 'warning',
        default => 'success',
    };
@endphp

@if($ticket)
    <div class="flex items-start justify-between gap-4 flex-wrap">
        <div class="min-w-0">
            <div class="label-xs">{{ __('cx.panel_label') }}</div>
            <div class="font-mono text-4xl font-semibold text-ink mt-0.5 truncate">{{ $ticket->codigo_ticket }}</div>
            <div class="text-md text-ink mt-1">{{ $ticket->asunto }}</div>
        </div>
        @if($ticket->prioridad_nombre)
            <x-ui.badge :tone="$tonoPrioridad($ticket->prioridad_codigo)">{{ $ticket->prioridad_nombre }}</x-ui.badge>
        @endif
    </div>

    @if($ticket->descripcion)
        <div class="mt-3 text-sm text-ink-600 whitespace-pre-line">{{ $ticket->descripcion }}</div>
    @endif

    <dl class="mt-4 grid grid-cols-[repeat(auto-fill,minmax(150px,1fr))] gap-y-3.5 gap-x-5">
        <div>
            <dt class="text-xs text-ink-500">{{ __('cx.categoria') }}</dt>
            <dd class="text-md font-medium text-ink mt-0.5">{{ $ticket->categoria_nombre ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-xs text-ink-500">{{ __('cx.sla') }}</dt>
            <dd class="text-md font-medium text-ink mt-0.5">{{ $ticket->sla_nombre ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-xs text-ink-500">{{ __('cx.escalamiento') }}</dt>
            <dd class="text-md font-medium text-ink mt-0.5">{{ $ticket->escalamiento_nombre ?? 'N1' }}</dd>
        </div>
        <div>
            <dt class="text-xs text-ink-500">{{ __('cx.reportado') }}</dt>
            <dd class="font-mono text-md font-medium text-ink mt-0.5">{{ hora_local($ticket->fecha_reporte) }}</dd>
        </div>
        @if($ticket->fecha_limite_sla)
            <div class="col-span-full">
                <dt class="text-xs text-ink-500">{{ __('cx.limite_sla') }}</dt>
                <dd class="font-mono text-md font-semibold text-ink mt-0.5">
                    {{ hora_local($ticket->fecha_limite_sla) }}
                    <span class="font-sans text-xs font-normal text-ink-500">· {{ hace_cuanto($ticket->fecha_limite_sla) }}</span>
                </dd>
            </div>
        @endif
    </dl>
@endif
