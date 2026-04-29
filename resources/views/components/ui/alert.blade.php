@props([
    'tone'  => 'info',  // info | success | warning | danger
    'title' => null,
])

@php
    $tones = [
        'info'    => 'border-info-200 bg-info-50 text-info-700',
        'success' => 'border-success-200 bg-success-50 text-success-700',
        'warning' => 'border-warning-200 bg-warning-50 text-warning-700',
        'danger'  => 'border-danger-200 bg-danger-50 text-danger-700',
    ][$tone] ?? 'border-info-200 bg-info-50 text-info-700';
@endphp

<div {{ $attributes->merge(['class' => "rounded-lg border px-3 py-2 text-sm {$tones}"]) }}>
    @if($title)
        <div class="font-semibold">{{ $title }}</div>
    @endif
    <div>{{ $slot }}</div>
</div>
