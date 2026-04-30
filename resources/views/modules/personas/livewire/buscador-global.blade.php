<div x-data="{ open: @entangle('abierto') }"
     x-on:keydown.window.ctrl.k.prevent="open = true; $nextTick(() => $refs.searchInput?.focus())"
     x-on:keydown.window.meta.k.prevent="open = true; $nextTick(() => $refs.searchInput?.focus())"
     x-on:keydown.escape.window="open = false"
     style="display:flex;align-items:center;">

    <button type="button"
            x-on:click="open = true; $nextTick(() => $refs.searchInput?.focus())"
            class="search-global">
        <x-ui.icon name="search" :size="14" />
        <span style="flex:1;text-align:left;">Buscar persona, caso, gestión…</span>
        <span class="kbd">Ctrl</span>
        <span class="kbd">K</span>
    </button>

    <div x-show="open" x-cloak class="scrim" x-on:click.self="open = false">
        <div class="modal-card" style="max-width:640px;padding:0;overflow:hidden;" x-on:click.stop>

            <div style="border-bottom:1px solid var(--border);padding:12px 16px;display:flex;align-items:center;gap:10px;">
                <x-ui.icon name="search" :size="16" style="color:var(--text-tertiary);" />
                <input x-ref="searchInput"
                       type="text"
                       wire:model.live.debounce.300ms="query"
                       placeholder="Buscar identificación o nombre de persona en el proyecto activo..."
                       style="flex:1;border:0;outline:none;background:transparent;font-size:14px;color:var(--text);">
            </div>

            <div style="max-height:420px;overflow-y:auto;">
                @if($proyectoActivo === null)
                    <div class="empty" style="padding:32px 16px;">
                        <div class="empty-desc">Selecciona un proyecto activo para buscar.</div>
                    </div>
                @elseif(mb_strlen(trim($query)) < 3)
                    <div class="empty" style="padding:32px 16px;">
                        <div class="empty-desc">
                            Escribe al menos 3 caracteres para buscar en el proyecto
                            <strong>{{ $proyectoActivo->nombre }}</strong>.
                        </div>
                    </div>
                @elseif($personas->isEmpty() && $casos->isEmpty())
                    <div class="empty" style="padding:32px 16px;">
                        <div class="empty-desc">Sin resultados en el proyecto activo.</div>
                    </div>
                @else
                    @if($personas->isNotEmpty())
                        <div class="label-xs" style="padding:12px 16px 4px;">Personas</div>
                        @foreach($personas as $p)
                            @php
                                $nombre = $p->tipo_persona === 'juridica'
                                    ? (string) ($p->razon_social ?? '')
                                    : trim((string) ($p->nombres ?? '').' '.(string) ($p->apellidos ?? ''));
                            @endphp
                            <a href="{{ route('proyectos.trabajo', ['proyecto_id' => $proyectoActivo->id, 'persona' => $p->public_id]) }}"
                               wire:navigate
                               x-on:click="open = false"
                               style="display:flex;align-items:center;gap:10px;padding:10px 16px;text-decoration:none;color:inherit;"
                               class="hover:bg-surface-100">
                                <x-ui.badge tone="neutral">{{ $p->tipo_identificacion_codigo ?? 'ID' }}</x-ui.badge>
                                <div style="flex:1;min-width:0;">
                                    <div style="font-size:13px;font-weight:500;color:var(--text);">{{ $nombre !== '' ? $nombre : '—' }}</div>
                                    <div class="font-mono" style="font-size:11px;color:var(--text-tertiary);">{{ $p->identificacion }}</div>
                                </div>
                            </a>
                        @endforeach
                    @endif

                    @if($casos->isNotEmpty())
                        <div class="label-xs" style="padding:12px 16px 4px;">Casos</div>
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
                               style="display:flex;align-items:center;gap:10px;padding:10px 16px;text-decoration:none;color:inherit;"
                               class="hover:bg-surface-100">
                                <x-ui.badge :tone="$tone">{{ ucfirst(str_replace('_', ' ', $c->tipo_caso)) }}</x-ui.badge>
                                <div style="flex:1;min-width:0;">
                                    <div style="font-size:13px;font-weight:500;color:var(--text);">{{ $nombre !== '' ? $nombre : '—' }}</div>
                                    <div style="font-size:11px;color:var(--text-tertiary);">
                                        {{ $c->cartera_nombre }} · {{ $c->estado_caso_nombre }}
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    @endif
                @endif
            </div>

            <div style="border-top:1px solid var(--border);background:var(--bg-subtle);padding:8px 16px;font-size:11px;color:var(--text-tertiary);display:flex;align-items:center;justify-content:space-between;">
                <span>proyecto: <strong style="color:var(--text);">{{ $proyectoActivo?->nombre ?? '—' }}</strong></span>
                <span><span class="kbd">Esc</span> cerrar</span>
            </div>
        </div>
    </div>
</div>
