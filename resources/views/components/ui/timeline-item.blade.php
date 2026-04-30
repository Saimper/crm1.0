@props([
    'tone'      => 'neutral',
    'timestamp' => null,
    'title'     => null,
    'badge'     => null,
])

@php
    $colorMap = [
        'success' => 'var(--success)',
        'warning' => 'var(--warning)',
        'danger'  => 'var(--danger)',
        'info'    => 'var(--info)',
        'primary' => 'var(--primary)',
        'neutral' => 'var(--text-muted)',
    ];
    $dotColor = $colorMap[$tone] ?? $colorMap['neutral'];
@endphp

<div {{ $attributes->merge(['class' => 'tl-item']) }}>
    <div class="tl-rail">
        <span class="tl-dot" style="border-color: {{ $dotColor }};"></span>
    </div>
    <div>
        <div class="tl-meta">
            @if($timestamp)<span class="timestamp-mono">{{ $timestamp }}</span>@endif
            @if($badge){!! $badge !!}@endif
        </div>
        @if($title)<div class="tl-title">{{ $title }}</div>@endif
        <div class="tl-detail">{{ $slot }}</div>
    </div>
</div>
