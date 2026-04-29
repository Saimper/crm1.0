@props([
    'title'    => '',
    'subtitle' => null,
    'back'     => null,
    'backLabel' => '← Volver',
])

<div class="flex items-start justify-between gap-4">
    <div class="min-w-0">
        <h1 class="text-xl font-semibold text-ink-900 leading-tight truncate">{{ $title }}</h1>
        @if($subtitle)
            <p class="mt-1 text-sm text-ink-500">{{ $subtitle }}</p>
        @endif
    </div>
    <div class="flex items-center gap-2">
        @isset($actions)
            {{ $actions }}
        @endisset
        @if($back)
            <a href="{{ $back }}" wire:navigate
               class="text-xs text-brand-700 hover:text-brand-800 hover:underline">{{ $backLabel }}</a>
        @endif
    </div>
</div>
