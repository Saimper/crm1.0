@props([
    'rows'   => 5,
    'height' => '14px',
])

{{--
    El hueco que ocupa lo que todavía no llegó. Se usa en la primera carga,
    cuando no hay una lista anterior que dejar en pantalla; si la hay, es mejor
    dejarla y poner la barra de `<x-ui.cargando>` encima.
--}}
<div {{ $attributes->merge(['class' => 'stack gap-2']) }} aria-hidden="true">
    @for($i = 0; $i < (int) $rows; $i++)
        <div class="skeleton" style="height: {{ $height }}; width: {{ [100, 92, 96, 88, 94][$i % 5] }}%;"></div>
    @endfor
</div>
