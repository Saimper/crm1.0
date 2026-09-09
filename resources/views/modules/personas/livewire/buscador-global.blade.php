<div x-data="{ open: @entangle('abierto') }"
     x-on:keydown.window.ctrl.k.prevent="open = true; $nextTick(() => $refs.searchInput?.focus())"
     x-on:keydown.window.meta.k.prevent="open = true; $nextTick(() => $refs.searchInput?.focus())"
     x-on:keydown.escape.window="open = false"
     class="flex items-center">

    <button type="button"
            x-on:click="open = true; $nextTick(() => $refs.searchInput?.focus())"
            class="search-global" aria-label="{{ __('personas.search_global_button', ['entidad' => $rotuloCaso]) }}" :aria-expanded="open" aria-haspopup="dialog">
        <x-ui.icon name="search" :size="14" />
        <span class="flex-1 min-w-0" style="text-align:left;">{{ __('personas.search_global_button', ['entidad' => $rotuloCaso]) }}</span>
        <span class="kbd">Ctrl</span>
        <span class="kbd">K</span>
    </button>

    <div x-show="open" x-cloak class="scrim" x-on:click.self="open = false">
        <div class="modal-card" role="dialog" aria-modal="true" aria-label="{{ __('personas.search_global_button', ['entidad' => $rotuloCaso]) }}" x-trap.inert.noscroll="open" style="max-width:640px;padding:0;overflow:hidden;" x-on:click.stop>

            <div class="toolbar">
                <x-ui.icon name="search" :size="16" class="text-ink-500" />
                <input x-ref="searchInput"
                       type="text"
                       wire:model.live.debounce.300ms="query"
                       placeholder="{{ __('personas.search_global_ph') }}"
                       aria-label="{{ __('personas.search_global_ph') }}"
                       class="flex-1 min-w-0 text-md"
                       style="border:0;outline:none;background:transparent;color:var(--text);">
                <button type="button" class="icon-btn" x-on:click="open = false" aria-label="{{ __('nav.close_search') }}">
                    <x-ui.icon name="x" :size="16" />
                </button>
            </div>

            {{-- La lista de antes se queda en pantalla mientras llega la nueva; sólo
                 esta barra dice que se está trabajando. --}}
            <x-ui.cargando />

            <div style="max-height:420px;overflow-y:auto;">
                @if($proyectoActivo === null)
                    <div class="empty" style="padding:32px 16px;">
                        <div class="empty-desc">{{ __('personas.search_select_project') }}</div>
                    </div>
                @elseif(mb_strlen(trim($query)) < 3)
                    <div class="empty" style="padding:32px 16px;">
                        <div class="empty-desc">
                            {{ __('personas.search_min_chars', ['proyecto' => $proyectoActivo->nombre]) }}
                        </div>
                    </div>
                @elseif($personas->isEmpty() && $casos->isEmpty())
                    <div class="empty" style="padding:32px 16px;">
                        <div class="empty-desc">{{ __('personas.search_no_results') }}</div>
                    </div>
                @else
                    @if($personas->isNotEmpty())
                        <div class="label-xs" style="padding:12px 16px 4px;">{{ __('personas.search_section_persons') }}</div>
                        @foreach($personas as $p)
                            @php
                                $nombre = $p->tipo_persona === 'juridica'
                                    ? (string) ($p->razon_social ?? '')
                                    : trim((string) ($p->nombres ?? '').' '.(string) ($p->apellidos ?? ''));
                            @endphp
                            <a href="{{ route('proyectos.trabajo', ['proyecto_id' => $proyectoActivo->id, 'persona' => $p->public_id]) }}"
                               wire:navigate
                               x-on:click="open = false"
                               style="gap:10px;padding:10px 16px;text-decoration:none;color:inherit;"
                               class="flex items-center hover:bg-surface-100">
                                <x-ui.badge tone="neutral">{{ $p->tipo_identificacion_codigo ?? 'ID' }}</x-ui.badge>
                                <div class="flex-1 min-w-0">
                                    <div class="text-base font-medium" style="color:var(--text);">{{ $nombre !== '' ? $nombre : '—' }}</div>
                                    <div class="font-mono text-xs text-ink-500">{{ $p->identificacion }}</div>
                                </div>
                            </a>
                        @endforeach
                    @endif

                    @if($casos->isNotEmpty())
                        <div class="label-xs" style="padding:12px 16px 4px;">{{ __('personas.search_section_cases', ['entidades' => $rotuloCasos]) }}</div>
                        @foreach($casos as $c)
                            @php
                                $nombre = $c->tipo_persona === 'juridica'
                                    ? (string) ($c->razon_social ?? '')
                                    : trim((string) ($c->nombres ?? '').' '.(string) ($c->apellidos ?? ''));
                                $tone = match ($c->tipo_caso) {
                                    'cobranza'   => 'warning',
                                    'ticket_cx'  => 'info',
                                    'lead_venta' => 'success',
                                    'servicio'   => 'primary',
                                    default      => 'neutral',
                                };
                            @endphp
                            <a href="{{ route('proyectos.trabajo', ['proyecto_id' => $proyectoActivo->id, 'persona' => $c->persona_public_id, 'caso' => $c->caso_public_id]) }}"
                               wire:navigate
                               x-on:click="open = false"
                               style="gap:10px;padding:10px 16px;text-decoration:none;color:inherit;"
                               class="flex items-center hover:bg-surface-100">
                                <x-ui.badge :tone="$tone">{{ ucfirst(str_replace('_', ' ', $c->tipo_caso)) }}</x-ui.badge>
                                <div class="flex-1 min-w-0">
                                    <div class="text-base font-medium" style="color:var(--text);">{{ $nombre !== '' ? $nombre : '—' }}</div>
                                    <div class="text-xs text-ink-500">
                                        {{ $c->cartera_nombre }} · {{ $c->estado_caso_nombre }}
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    @endif
                @endif
            </div>

            <div class="flex items-center justify-between text-xs text-ink-500"
                 style="border-top:1px solid var(--border);background:var(--bg-subtle);padding:8px 16px;">
                <span>{{ __('personas.search_project_label') }} <strong style="color:var(--text);">{{ $proyectoActivo?->nombre ?? '—' }}</strong></span>
                <span><span class="kbd">Esc</span> {{ __('personas.search_close_hint') }}</span>
            </div>
        </div>
    </div>
</div>
