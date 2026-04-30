@props([
    'title'    => '',
    'subtitle' => null,
    'back'     => null,
    'backLabel' => '← Volver',
])

<div class="page-header">
    <div class="min-w-0 flex-1">
        <h1 class="page-title truncate">{{ $title }}</h1>
        @if($subtitle)
            <p class="page-subtitle">{{ $subtitle }}</p>
        @endif
    </div>
    <div class="flex items-center gap-2">
        @isset($actions)
            {{ $actions }}
        @endisset
        @if($back)
            <a href="{{ $back }}" wire:navigate class="btn btn-ghost btn-sm">{{ $backLabel }}</a>
        @endif
    </div>
</div>
