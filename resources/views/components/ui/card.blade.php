@props([
    'title'   => null,
    'subtitle'=> null,
    'padding' => 'card-pad',  // card-pad | card-pad-sm | none
])

@php
    $padCls = $padding === 'none' ? '' : $padding;
@endphp

<div {{ $attributes->merge(['class' => 'card']) }}>
    @if($title || $subtitle || isset($header))
        <div class="card-header">
            @if(isset($header))
                {{ $header }}
            @else
                <div class="flex items-start justify-between gap-3 flex-1">
                    <div>
                        @if($title)
                            <h3 class="card-title">{{ $title }}</h3>
                        @endif
                        @if($subtitle)
                            <p class="text-sm mt-0.5" style="color: var(--text-tertiary);">{{ $subtitle }}</p>
                        @endif
                    </div>
                    @isset($actions)
                        <div class="flex items-center gap-2">{{ $actions }}</div>
                    @endisset
                </div>
            @endif
        </div>
    @endif

    <div @class([$padCls => $padCls])>
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="card-header" style="border-top: 1px solid var(--border); border-bottom: 0; background: var(--bg-subtle); border-radius: 0 0 8px 8px;">
            {{ $footer }}
        </div>
    @endisset
</div>
