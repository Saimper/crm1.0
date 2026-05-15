@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full ps-3 pe-4 py-2 border-l-4 border-brand-400 text-start text-base font-medium text-brand-700 bg-brand-50 focus:outline-none focus:text-brand-800 focus:bg-brand-100 focus:border-brand-700 transition duration-150 ease-in-out'
            : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-ink-600 hover:text-ink-800 hover:bg-ink-50 hover:border-ink-300 focus:outline-none focus:text-ink-800 focus:bg-ink-50 focus:border-ink-300 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
