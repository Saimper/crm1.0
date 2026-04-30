@php
    /** @var object|null $servicio */
@endphp

@if($servicio)
    <div class="rounded-md border border-blue-200 bg-blue-50 p-4 space-y-3">
        <div class="flex items-start justify-between gap-4">
            <div>
                <div class="text-[10px] uppercase tracking-wider text-blue-700">Caso de servicio · Servicio técnico</div>
                <div class="text-lg font-semibold text-blue-900 font-mono">{{ $servicio->codigo_servicio }}</div>
                @if($servicio->tipo_accion_nombre)
                    <div class="text-sm text-blue-900 mt-0.5">{{ $servicio->tipo_accion_nombre }}</div>
                @endif
            </div>
            @if($servicio->estado_tecnico_nombre)
                <span class="inline-block rounded px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800">
                    {{ $servicio->estado_tecnico_nombre }}
                </span>
            @endif
        </div>

        <dl class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
            <div class="col-span-2 sm:col-span-4">
                <dt class="text-blue-700">Dirección</dt>
                <dd class="font-medium text-blue-900">{{ $servicio->direccion_servicio ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-blue-700">Técnico asignado</dt>
                <dd class="font-medium text-blue-900">{{ $servicio->tecnico_asignado ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-blue-700">Solicitud</dt>
                <dd class="font-medium text-blue-900">
                    {{ \Illuminate\Support\Carbon::parse($servicio->fecha_solicitud)->format('d/m/Y') }}
                </dd>
            </div>
            @if($servicio->fecha_programada)
                <div class="col-span-2">
                    <dt class="text-blue-700">Programada</dt>
                    <dd class="font-semibold text-blue-900">
                        {{ \Illuminate\Support\Carbon::parse($servicio->fecha_programada)->format('d/m/Y H:i') }}
                        @php $diff = \Illuminate\Support\Carbon::parse($servicio->fecha_programada)->diffForHumans(); @endphp
                        <span class="text-[10px] text-blue-700">· {{ $diff }}</span>
                    </dd>
                </div>
            @endif
        </dl>
    </div>
@endif
