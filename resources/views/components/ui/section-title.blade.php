@props([
    'title' => '',
    'hint'  => null,
])

<div class="flex items-end justify-between gap-3 mb-3">
    <div>
        <h2 class="text-xs font-semibold uppercase tracking-wider text-ink-500">{{ $title }}</h2>
        @if($hint)
            <p class="mt-0.5 text-xs text-ink-400">{{ $hint }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
