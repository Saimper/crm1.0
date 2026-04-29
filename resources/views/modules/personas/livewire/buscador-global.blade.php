<div x-data="{ open: @entangle('abierto') }"
     x-on:keydown.window.ctrl.k.prevent="open = true; $nextTick(() => $refs.searchInput?.focus())"
     x-on:keydown.window.meta.k.prevent="open = true; $nextTick(() => $refs.searchInput?.focus())"
     x-on:keydown.escape.window="open = false">

    <button type="button"
            x-on:click="open = true; $nextTick(() => $refs.searchInput?.focus())"
            class="inline-flex items-center gap-2 rounded-md border border-gray-200 bg-white px-3 py-1.5 text-xs text-gray-500 hover:bg-gray-50">
        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"/>
        </svg>
        <span>Buscar</span>
        <kbd class="ml-2 rounded border border-gray-300 bg-gray-50 px-1 text-[10px] font-semibold text-gray-600">⌘K</kbd>
    </button>

    <div x-show="open"
         x-cloak
         class="fixed inset-0 z-40 flex items-start justify-center bg-gray-900/40 pt-24 px-4"
         x-on:click.self="open = false">

        <div class="w-full max-w-2xl overflow-hidden rounded-lg bg-white shadow-xl"
             x-on:click.stop>

            <div class="border-b border-gray-200 px-4 py-3">
                <input x-ref="searchInput"
                       type="text"
                       wire:model.live.debounce.300ms="query"
                       placeholder="Buscar identificación o nombre de persona en el proyecto activo..."
                       class="w-full border-0 focus:ring-0 text-sm text-gray-900 placeholder-gray-400">
            </div>

            <div class="max-h-96 overflow-y-auto">
                @if($proyectoActivo === null)
                    <div class="p-6 text-center text-xs text-gray-500">
                        Selecciona un proyecto activo para buscar.
                    </div>
                @elseif(mb_strlen(trim($query)) < 3)
                    <div class="p-6 text-center text-xs text-gray-500">
                        Escribe al menos 3 caracteres para buscar en el proyecto <strong>{{ $proyectoActivo->nombre }}</strong>.
                    </div>
                @elseif($personas->isEmpty() && $casos->isEmpty())
                    <div class="p-6 text-center text-xs text-gray-500">
                        Sin resultados en el proyecto activo.
                    </div>
                @else
                    @if($personas->isNotEmpty())
                        <div class="px-4 pt-3 pb-1 text-[10px] font-semibold uppercase tracking-wider text-gray-500">Personas</div>
                        <ul class="divide-y divide-gray-100">
                            @foreach($personas as $p)
                                @php
                                    $nombre = $p->tipo_persona === 'juridica'
                                        ? (string) ($p->razon_social ?? '')
                                        : trim((string) ($p->nombres ?? '').' '.(string) ($p->apellidos ?? ''));
                                @endphp
                                <li>
                                    <a href="{{ route('proyectos.trabajo', ['proyecto_id' => $proyectoActivo->id, 'persona' => $p->public_id]) }}"
                                       wire:navigate
                                       x-on:click="open = false"
                                       class="flex items-center gap-3 px-4 py-2 text-sm hover:bg-indigo-50">
                                        <span class="inline-block rounded bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-700">
                                            {{ $p->tipo_identificacion_codigo ?? 'ID' }}
                                        </span>
                                        <div class="flex-1">
                                            <div class="font-medium text-gray-900">{{ $nombre !== '' ? $nombre : '—' }}</div>
                                            <div class="text-xs text-gray-500">{{ $p->identificacion }}</div>
                                        </div>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if($casos->isNotEmpty())
                        <div class="px-4 pt-3 pb-1 text-[10px] font-semibold uppercase tracking-wider text-gray-500">Casos</div>
                        <ul class="divide-y divide-gray-100">
                            @foreach($casos as $c)
                                @php
                                    $nombre = $c->tipo_persona === 'juridica'
                                        ? (string) ($c->razon_social ?? '')
                                        : trim((string) ($c->nombres ?? '').' '.(string) ($c->apellidos ?? ''));
                                    $tipoColor = match ($c->tipo_caso) {
                                        'cobranza'   => 'bg-amber-100 text-amber-800',
                                        'ticket_cx'  => 'bg-sky-100 text-sky-800',
                                        'lead_venta' => 'bg-emerald-100 text-emerald-800',
                                        'servicio'   => 'bg-violet-100 text-violet-800',
                                        default      => 'bg-gray-100 text-gray-700',
                                    };
                                @endphp
                                <li>
                                    <a href="{{ route('proyectos.trabajo', ['proyecto_id' => $proyectoActivo->id, 'persona' => $c->persona_public_id, 'caso' => $c->caso_public_id]) }}"
                                       wire:navigate
                                       x-on:click="open = false"
                                       class="flex items-center gap-3 px-4 py-2 text-sm hover:bg-indigo-50">
                                        <span class="inline-block rounded px-2 py-0.5 text-[10px] font-medium {{ $tipoColor }}">
                                            {{ ucfirst(str_replace('_', ' ', $c->tipo_caso)) }}
                                        </span>
                                        <div class="flex-1">
                                            <div class="font-medium text-gray-900">{{ $nombre !== '' ? $nombre : '—' }}</div>
                                            <div class="text-xs text-gray-500">
                                                {{ $c->cartera_nombre }} · {{ $c->estado_caso_nombre }}
                                            </div>
                                        </div>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                @endif
            </div>

            <div class="border-t border-gray-200 bg-gray-50 px-4 py-2 text-[10px] text-gray-500 flex items-center justify-between">
                <span>proyecto: <strong>{{ $proyectoActivo?->nombre ?? '—' }}</strong></span>
                <span>
                    <kbd class="rounded border border-gray-300 bg-white px-1 font-semibold">Esc</kbd> cerrar
                </span>
            </div>
        </div>
    </div>
</div>
