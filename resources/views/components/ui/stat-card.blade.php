@props([
    'label' => '',
    'value' => '0',
    'hint'  => null,
    'tone'  => 'neutral',  // neutral | success | warning | danger | info | brand
    'delta' => null,       // { dir: up|down|flat, value: '+12%' }
    'icon'  => null,
])

@php
    $deltaCls = $delta && in_array($delta['dir'] ?? '', ['up','down','flat'], true)
        ? 'kpi-delta '.$delta['dir']
        : null;
@endphp

<div {{ $attributes->merge(['class' => 'kpi-card']) }}>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <div class="kpi-label">{{ $label }}</div>
            <div class="kpi-value">{{ $value }}</div>
            @if($delta)
                <div class="{{ $deltaCls }}">{{ $delta['value'] ?? '' }}</div>
            @elseif($hint)
                <div class="mt-2 text-sm" style="color: var(--text-tertiary);">{{ $hint }}</div>
            @endif
        </div>
        @if($icon)
            <div class="flex-shrink-0 h-9 w-9 rounded-md flex items-center justify-center"
                 style="background: var(--bg-subtle); color: var(--text-tertiary);">
                {!! $icon !!}
            </div>
        @endif
    </div>
</div>
