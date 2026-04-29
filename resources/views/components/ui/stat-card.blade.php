@props([
    'label' => '',
    'value' => '0',
    'hint'  => null,
    'tone'  => 'neutral',  // neutral | success | warning | danger | info | brand
    'icon'  => null,
])

@php
    $toneClasses = [
        'neutral' => 'bg-surface-50 text-ink-700',
        'brand'   => 'bg-brand-50 text-brand-700',
        'success' => 'bg-success-50 text-success-700',
        'warning' => 'bg-warning-50 text-warning-700',
        'danger'  => 'bg-danger-50 text-danger-700',
        'info'    => 'bg-info-50 text-info-700',
    ][$tone] ?? 'bg-surface-50 text-ink-700';

    $valueTone = [
        'neutral' => 'text-ink-900',
        'brand'   => 'text-brand-700',
        'success' => 'text-success-700',
        'warning' => 'text-warning-700',
        'danger'  => 'text-danger-700',
        'info'    => 'text-info-700',
    ][$tone] ?? 'text-ink-900';
@endphp

<div {{ $attributes->merge(['class' => 'rounded-xl border border-surface-border bg-white shadow-card p-5']) }}>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <div class="text-[11px] uppercase tracking-wider font-medium text-ink-500">{{ $label }}</div>
            <div class="mt-2 text-2xl font-semibold leading-none {{ $valueTone }}">{{ $value }}</div>
            @if($hint)
                <div class="mt-2 text-xs text-ink-500">{{ $hint }}</div>
            @endif
        </div>
        @if($icon)
            <div class="flex-shrink-0 h-10 w-10 rounded-lg {{ $toneClasses }} flex items-center justify-center">
                {!! $icon !!}
            </div>
        @endif
    </div>
</div>
