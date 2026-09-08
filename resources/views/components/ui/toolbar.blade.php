@props([
    'count' => null,   // recuento de resultados, a la derecha
])

{{--
    La barra de filtros de un listado.

    Lo que va en el slot se coloca de izquierda a derecha; el recuento y lo que
    venga en `acciones` quedan pegados a la derecha. Nació de once pantallas que
    escribían la misma barra a mano con seis atributos `style`.
--}}
<div {{ $attributes->merge(['class' => 'toolbar']) }}>
    {{ $slot }}

    <span class="toolbar-spacer"></span>

    @if($count !== null)
        <span class="toolbar-count">{{ $count }}</span>
    @endif

    @isset($acciones)
        {{ $acciones }}
    @endisset
</div>
