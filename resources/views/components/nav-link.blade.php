@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center px-1 pt-1 border-b-2 border-brand-400 text-sm font-medium leading-5 text-ink-900 focus:outline-none focus:border-brand-700 transition duration-150 ease-in-out'
            : 'inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-ink-500 hover:text-ink-700 hover:border-ink-300 focus:outline-none focus:text-ink-700 focus:border-ink-300 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
