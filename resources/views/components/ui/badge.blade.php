@props([
    'tone' => 'neutral',  // neutral | success | warning | danger | info | brand | accent
    'size' => 'md',       // sm | md
])

@php
    $tones = [
        'neutral' => 'bg-surface-100 text-ink-700 ring-surface-border',
        'brand'   => 'bg-brand-50 text-brand-700 ring-brand-200',
        'accent'  => 'bg-accent-50 text-accent-700 ring-accent-500/20',
        'success' => 'bg-success-50 text-success-700 ring-success-500/20',
        'warning' => 'bg-warning-50 text-warning-700 ring-warning-500/20',
        'danger'  => 'bg-danger-50 text-danger-700 ring-danger-500/20',
        'info'    => 'bg-info-50 text-info-700 ring-info-500/20',
    ][$tone] ?? 'bg-surface-100 text-ink-700 ring-surface-border';

    $sizes = [
        'sm' => 'px-1.5 py-0.5 text-[10px]',
        'md' => 'px-2 py-0.5 text-xs',
    ][$size] ?? 'px-2 py-0.5 text-xs';
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full font-medium ring-1 ring-inset {$tones} {$sizes}"]) }}>
    {{ $slot }}
</span>
