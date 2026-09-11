{{-- Filas del resumen de un servicio técnico. Van dentro del <dl class="vt-dl">
     de la Vista de Trabajo, que añade las comunes (estado, asesor, cartera). --}}
@php /** @var object|null $servicio */ @endphp

@if($servicio)
    <dt>{{ __('servicio.codigo') }}</dt>
    <dd class="font-mono font-semibold text-ink">{{ $servicio->codigo_servicio }}</dd>

    @if($servicio->tipo_accion_nombre)
        <dt>{{ __('servicio.tipo_accion') }}</dt>
        <dd>{{ $servicio->tipo_accion_nombre }}</dd>
    @endif

    @if($servicio->estado_tecnico_nombre)
        <dt>{{ __('servicio.estado_tecnico') }}</dt>
        <dd><x-ui.badge tone="primary" size="sm">{{ $servicio->estado_tecnico_nombre }}</x-ui.badge></dd>
    @endif

    @if($servicio->fecha_programada)
        <dt>{{ __('servicio.programada') }}</dt>
        <dd>
            <span class="font-mono font-semibold">{{ hora_local($servicio->fecha_programada) }}</span>
            <span class="text-ink-500">· {{ hace_cuanto($servicio->fecha_programada) }}</span>
        </dd>
    @endif

    <dt>{{ __('servicio.tecnico_asignado') }}</dt>
    <dd>{{ $servicio->tecnico_asignado ?? '—' }}</dd>
@endif
