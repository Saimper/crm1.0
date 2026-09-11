@php
    /** @var object|null $servicio */
@endphp

@if($servicio)
    <div class="flex items-start justify-between gap-4 flex-wrap">
        <div class="min-w-0">
            <div class="label-xs">{{ __('servicio.panel_label') }}</div>
            <div class="font-mono text-4xl font-semibold text-ink mt-0.5 truncate">{{ $servicio->codigo_servicio }}</div>
            @if($servicio->tipo_accion_nombre)
                <div class="text-md text-ink mt-1">{{ $servicio->tipo_accion_nombre }}</div>
            @endif
        </div>
        @if($servicio->estado_tecnico_nombre)
            <x-ui.badge tone="primary">{{ $servicio->estado_tecnico_nombre }}</x-ui.badge>
        @endif
    </div>

    <dl class="mt-4 grid grid-cols-[repeat(auto-fill,minmax(150px,1fr))] gap-y-3.5 gap-x-5">
        <div class="col-span-full">
            <dt class="text-xs text-ink-500">{{ __('servicio.direccion') }}</dt>
            <dd class="text-md font-medium text-ink mt-0.5">{{ $servicio->direccion_servicio ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-xs text-ink-500">{{ __('servicio.tecnico_asignado') }}</dt>
            <dd class="text-md font-medium text-ink mt-0.5">{{ $servicio->tecnico_asignado ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-xs text-ink-500">{{ __('servicio.solicitud') }}</dt>
            <dd class="font-mono text-md font-medium text-ink mt-0.5">{{ fecha_local($servicio->fecha_solicitud) }}</dd>
        </div>
        @if($servicio->fecha_programada)
            <div class="col-span-2">
                <dt class="text-xs text-ink-500">{{ __('servicio.programada') }}</dt>
                <dd class="font-mono text-md font-semibold text-ink mt-0.5">
                    {{ hora_local($servicio->fecha_programada) }}
                    <span class="font-sans text-xs font-normal text-ink-500">· {{ hace_cuanto($servicio->fecha_programada) }}</span>
                </dd>
            </div>
        @endif
    </dl>
@endif
