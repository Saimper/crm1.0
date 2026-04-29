<div x-data="{}"
     @keydown.window.ctrl.k.prevent="$wire.abrir()"
     @keydown.window.meta.k.prevent="$wire.abrir()"
     @keydown.escape.window="if ($wire.abierto) $wire.cerrar()">

    <button type="button"
            @click="$wire.abrir()"
            class="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 19a8 8 0 1 1 0-16 8 8 0 0 1 0 16z"/>
        </svg>
        <span>Buscar</span>
        <kbd class="ml-2 rounded border border-gray-300 bg-gray-50 px-1.5 py-0.5 text-xs text-gray-600">⌘K</kbd>
    </button>

    @if($abierto)
        <div class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 pt-24"
             wire:click.self="cerrar">
            <div class="w-full max-w-2xl overflow-hidden rounded-lg bg-white shadow-2xl">
                <div class="border-b border-gray-200">
                    <input type="text"
                           wire:model.live.debounce.250ms="query"
                           placeholder="Identificación, nombre, razón social o número de préstamo..."
                           autofocus
                           class="w-full border-0 bg-transparent px-4 py-4 text-base text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-0">
                </div>

                <div class="max-h-96 overflow-y-auto p-2">
                    @php
                        $conTexto = strlen(trim($query)) >= 3;
                        $sinResultados = $conTexto && $clientes->isEmpty() && $productos->isEmpty();
                    @endphp

                    @if($clientes->isNotEmpty())
                        <div class="px-2 py-1 text-xs font-semibold uppercase tracking-wider text-gray-500">Clientes</div>
                        @foreach($clientes as $c)
                            @php
                                $nombre = $c->tipo_persona === 'juridica'
                                    ? (string) $c->razon_social
                                    : trim((string) ($c->nombres ?? '').' '.(string) ($c->apellidos ?? ''));
                            @endphp
                            <a href="{{ route('trabajo', ['cliente' => $c->public_id]) }}"
                               wire:navigate
                               class="block rounded px-3 py-2 hover:bg-gray-100">
                                <div class="font-medium text-gray-900">{{ $nombre }}</div>
                                <div class="text-sm text-gray-600">{{ $c->identificacion }}</div>
                            </a>
                        @endforeach
                    @endif

                    @if($productos->isNotEmpty())
                        <div class="mt-2 px-2 py-1 text-xs font-semibold uppercase tracking-wider text-gray-500">Productos</div>
                        @foreach($productos as $p)
                            @php
                                $nombre = $p->tipo_persona === 'juridica'
                                    ? (string) $p->razon_social
                                    : trim((string) ($p->nombres ?? '').' '.(string) ($p->apellidos ?? ''));
                            @endphp
                            <a href="{{ route('trabajo', ['cliente' => $p->cliente_public_id, 'producto' => $p->producto_public_id]) }}"
                               wire:navigate
                               class="block rounded px-3 py-2 hover:bg-gray-100">
                                <div class="font-medium text-gray-900">Préstamo {{ $p->numero_prestamo }}</div>
                                <div class="text-sm text-gray-600">{{ $nombre }} · {{ $p->identificacion }}</div>
                            </a>
                        @endforeach
                    @endif

                    @if($sinResultados)
                        <div class="p-8 text-center text-sm text-gray-600">Sin resultados.</div>
                    @elseif(! $conTexto)
                        <div class="p-8 text-center text-sm text-gray-500">Escribe al menos 3 caracteres.</div>
                    @endif
                </div>

                <div class="border-t border-gray-200 bg-gray-50 px-4 py-2 text-xs text-gray-600">
                    <kbd class="rounded border border-gray-300 bg-white px-1.5 py-0.5">Esc</kbd> cerrar
                </div>
            </div>
        </div>
    @endif
</div>
