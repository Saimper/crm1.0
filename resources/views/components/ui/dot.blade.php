@props(['tone' => 'neutral'])

<span {{ $attributes->merge(['class' => "dot dot-{$tone}"]) }}></span>
