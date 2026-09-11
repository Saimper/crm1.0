{{-- Filas del resumen de un ticket. Van dentro del <dl class="vt-dl"> de la
     Vista de Trabajo, que añade las comunes (estado, asesor, cartera). --}}
@php
    /** @var object|null $ticket */
    $tonoPrioridad = fn (?string $codigo): string => match (strtoupper((string) $codigo)) {
        'URGENTE' => 'danger',
        'ALTA', 'MEDIA' => 'warning',
        default => 'success',
    };
@endphp

@if($ticket)
    <dt>{{ __('cx.ticket') }}</dt>
    <dd class="font-mono font-semibold text-ink">{{ $ticket->codigo_ticket }}</dd>

    <dt>{{ __('cx.asunto') }}</dt>
    <dd class="text-ink">{{ $ticket->asunto }}</dd>

    @if($ticket->prioridad_nombre)
        <dt>{{ __('cx.prioridad') }}</dt>
        <dd><x-ui.badge :tone="$tonoPrioridad($ticket->prioridad_codigo)" size="sm">{{ $ticket->prioridad_nombre }}</x-ui.badge></dd>
    @endif

    @if($ticket->fecha_limite_sla)
        <dt>{{ __('cx.limite_sla') }}</dt>
        <dd>
            <span class="font-mono font-semibold">{{ hora_local($ticket->fecha_limite_sla) }}</span>
            <span class="text-ink-500">· {{ hace_cuanto($ticket->fecha_limite_sla) }}</span>
        </dd>
    @endif

    <dt>{{ __('cx.escalamiento') }}</dt>
    <dd>{{ $ticket->escalamiento_nombre ?? 'N1' }}@if($ticket->sla_nombre) <span class="text-ink-500">· {{ __('cx.sla') }} {{ $ticket->sla_nombre }}</span>@endif</dd>
@endif
