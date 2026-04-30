@props([
    'cols' => 2,  // 1 | 2
])

@php
    $gridCls = $cols === 1
        ? 'grid grid-cols-[auto_1fr] gap-x-3 gap-y-1.5 text-sm'
        : 'grid grid-cols-2 gap-3 text-sm';
@endphp

<dl {{ $attributes->merge(['class' => $gridCls]) }}>
    {{ $slot }}
</dl>
