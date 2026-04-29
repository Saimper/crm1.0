@props([
    'title' => null,
    'subtitle' => null,
    'padding' => 'p-6',
])

<div {{ $attributes->merge(['class' => 'rounded-xl border border-surface-border bg-white shadow-card']) }}>
    @if($title || $subtitle || isset($header))
        <div class="px-6 py-4 border-b border-surface-border">
            @if(isset($header))
                {{ $header }}
            @else
                <div class="flex items-start justify-between gap-3">
                    <div>
                        @if($title)
                            <h3 class="text-sm font-semibold text-ink-800">{{ $title }}</h3>
                        @endif
                        @if($subtitle)
                            <p class="text-xs text-ink-500 mt-0.5">{{ $subtitle }}</p>
                        @endif
                    </div>
                    @isset($actions)
                        <div class="flex items-center gap-2">{{ $actions }}</div>
                    @endisset
                </div>
            @endif
        </div>
    @endif

    <div class="{{ $padding }}">
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="px-6 py-3 border-t border-surface-border bg-surface-50 rounded-b-xl">
            {{ $footer }}
        </div>
    @endisset
</div>
