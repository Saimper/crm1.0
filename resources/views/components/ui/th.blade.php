@props([
    'align' => 'left',  // left | right | center
])

@php
    $alignClass = [
        'left'   => 'text-left',
        'right'  => 'text-right',
        'center' => 'text-center',
    ][$align] ?? 'text-left';
@endphp

<th {{ $attributes->merge(['class' => "px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-ink-500 {$alignClass}"]) }}>
    {{ $slot }}
</th>
