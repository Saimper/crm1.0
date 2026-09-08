@php
    if (! isset($scrollTo)) {
        $scrollTo = 'body';
    }

    $desplazar = $scrollTo === false
        ? ''
        : "(\$el.closest('{$scrollTo}') || document.querySelector('{$scrollTo}')).scrollIntoView()";

    $pagina = $paginator->getPageName();
@endphp

{{--
    La paginación de todos los listados.

    La que venía con Livewire pintaba con clases de color crudas de Tailwind
    —`text-gray-400`, `border-gray-200`— así que era lo único de la aplicación
    que no seguía los tokens: un cambio de tema la habría dejado atrás sin que
    nadie lo notara. Ahora usa las clases `.pager-*`, y por eso vive aquí
    publicada y no en el paquete.
--}}
<div>
    @if ($paginator->hasPages())
        <nav role="navigation" aria-label="{{ __('common.pagination') }}" class="pager">
            <p class="pager-info">
                {{ __('common.pagination_range', [
                    'desde' => $paginator->firstItem(),
                    'hasta' => $paginator->lastItem(),
                    'total' => $paginator->total(),
                ]) }}
            </p>

            <span class="pager-links">
                @if ($paginator->onFirstPage())
                    <span class="pager-link is-disabled" aria-disabled="true">{!! __('pagination.previous') !!}</span>
                @else
                    <button type="button" class="pager-link" wire:loading.attr="disabled"
                            wire:click="previousPage('{{ $pagina }}')" x-on:click="{{ $desplazar }}">
                        {!! __('pagination.previous') !!}
                    </button>
                @endif

                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="pager-gap">{{ $element }}</span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span class="pager-link is-active" aria-current="page">{{ $page }}</span>
                            @else
                                <button type="button" class="pager-link" wire:loading.attr="disabled"
                                        wire:click="gotoPage({{ $page }}, '{{ $pagina }}')" x-on:click="{{ $desplazar }}">
                                    {{ $page }}
                                </button>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                @if ($paginator->hasMorePages())
                    <button type="button" class="pager-link" wire:loading.attr="disabled"
                            wire:click="nextPage('{{ $pagina }}')" x-on:click="{{ $desplazar }}">
                        {!! __('pagination.next') !!}
                    </button>
                @else
                    <span class="pager-link is-disabled" aria-disabled="true">{!! __('pagination.next') !!}</span>
                @endif
            </span>
        </nav>
    @endif
</div>
