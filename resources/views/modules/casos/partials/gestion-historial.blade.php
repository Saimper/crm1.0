{{-- Una gestión en el historial. El color del punto sale de
     `resultados.es_contacto_efectivo`, que es lo que el proyecto declara de
     cada resultado, no de comparar nombres. --}}
@php
    /** @var object $g */
    $campos = $campos ?? [];
    $clave = $clave ?? 'gestion';
@endphp

<x-ui.timeline-item :tone="$g->es_contacto_efectivo ? 'success' : 'neutral'"
                    :timestamp="hora_local($g->creada_en)"
                    wire:key="{{ $clave }}-{{ $g->id }}">
    <x-slot:title>
        <strong>{{ $g->resultado_nombre ?? '—' }}</strong>
        <span class="text-ink-500">· {{ $g->tipo_gestion_nombre ?? '—' }}</span>
    </x-slot:title>

    @if($g->notas)
        <div class="tl-detail">{{ $g->notas }}</div>
    @endif

    <div class="tl-meta">
        <span>
            {{ $g->canal_nombre ?? '—' }} · {{ $g->usuario_nombre ?? '—' }}
            @if($g->duracion_segundos)
                · {{ (int) floor($g->duracion_segundos / 60) }}m {{ $g->duracion_segundos % 60 }}s
            @endif
        </span>
        @if($g->motivo_no_contacto_nombre)
            <x-ui.badge tone="warning" size="sm">{{ __('casos.no_contact_badge', ['motivo' => $g->motivo_no_contacto_nombre]) }}</x-ui.badge>
        @endif
        @if($g->causa_nombre)
            <x-ui.badge tone="info" size="sm">{{ __('casos.cause_badge', ['causa' => $g->causa_nombre]) }}</x-ui.badge>
        @endif
    </div>

    {{-- Valores de campos personalizados ámbito gestión × tipo_gestion. --}}
    @if($campos !== [])
        <dl class="mt-2 pt-2 border-t border-dashed border-ink-200 grid grid-cols-[auto_minmax(0,1fr)] gap-x-3 gap-y-[3px] text-sm">
            @foreach($campos as $cp)
                <dt class="text-ink-500">{{ $cp['etiqueta'] }}</dt>
                <dd class="text-ink">{{ $cp['valor'] }}</dd>
            @endforeach
        </dl>
    @endif
</x-ui.timeline-item>
