<div style="display:flex;flex-direction:column;gap:14px;">

    {{-- Filtro de periodo: una sola fila encima de todo, como manda el patrón. --}}
    <div style="display:flex;align-items:baseline;justify-content:space-between;gap:12px;flex-wrap:wrap;">
        <div>
            <div style="font-size:15px;font-weight:600;color:var(--text);">{{ __('reportes.panel_titulo') }}</div>
            <div style="font-size:12px;color:var(--text-tertiary);">{{ $etiquetaRango }}</div>
        </div>
        <div style="display:inline-flex;border:1px solid var(--border);border-radius:8px;overflow:hidden;">
            @foreach(['hoy','semana','mes'] as $r)
                <button type="button" wire:click="cambiarRango('{{ $r }}')"
                        style="padding:5px 12px;font-size:12px;border:0;cursor:pointer;
                               background:{{ $rango === $r ? 'var(--primary-soft)' : 'transparent' }};
                               color:{{ $rango === $r ? 'var(--primary)' : 'var(--text-secondary)' }};
                               font-weight:{{ $rango === $r ? '600' : '400' }};">
                    {{ __('reportes.panel_rango_'.$r) }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- Cifras. Cada una es un número solo: un tile, no un gráfico.
         El color va por ESTADO (cumplido/roto), y siempre acompañado de su
         etiqueta, nunca el color a solas. --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        @php
            $tiles = [
                ['clave' => 'gestiones', 'valor' => $totalGestiones, 'color' => 'var(--text)',         'punto' => null],
                ['clave' => 'vigentes',  'valor' => $vigentes,       'color' => 'var(--text)',         'punto' => 'var(--info)'],
                ['clave' => 'cumplidas', 'valor' => $cumplidas,      'color' => 'var(--success-text)', 'punto' => 'var(--success)'],
                ['clave' => 'rotas',     'valor' => $rotas,          'color' => 'var(--danger-text)',  'punto' => 'var(--danger)'],
            ];
        @endphp
        @foreach($tiles as $t)
            <div class="card" style="padding:14px;">
                <div style="display:flex;align-items:center;gap:6px;">
                    @if($t['punto'])
                        <span style="width:7px;height:7px;border-radius:50%;background:{{ $t['punto'] }};flex-shrink:0;"></span>
                    @endif
                    <span class="label-xs">{{ __('reportes.panel_'.$t['clave']) }}</span>
                </div>
                <div style="font-size:26px;font-weight:600;line-height:1.15;margin-top:6px;
                            font-variant-numeric:tabular-nums;color:{{ $t['color'] }};">
                    {{ number_format($t['valor']) }}
                </div>
            </div>
        @endforeach
    </div>

    {{-- Vencidas y sin resolver: no es un cuarto estado, es una alerta. Solo
         aparece cuando hay algo que atender, para no ocupar sitio en vano. --}}
    @if($vencidasSinResolver > 0)
        <div class="card" style="padding:12px 14px;display:flex;align-items:center;gap:10px;
                                 border-color:var(--warning);background:var(--warning-soft);">
            <x-ui.icon name="alert-triangle" :size="15" style="color:var(--warning-text);flex-shrink:0;" />
            <span style="font-size:13px;color:var(--warning-text);">
                {{ trans_choice('reportes.panel_vencidas', $vencidasSinResolver, ['n' => number_format($vencidasSinResolver)]) }}
            </span>
        </div>
    @endif

    {{-- Gestiones por usuario. Una sola serie, así que un solo tono y sin
         leyenda: el título ya dice qué se mide. Barras horizontales porque los
         nombres son largos y desiguales, y ordenadas de mayor a menor porque lo
         que se busca es el ranking. --}}
    <div class="card" style="padding:16px;">
        <div style="display:flex;align-items:baseline;justify-content:space-between;margin-bottom:14px;">
            <div style="font-size:13px;font-weight:600;color:var(--text);">{{ __('reportes.panel_por_usuario') }}</div>
            <a href="{{ route('proyectos.reportes.operativos', $proyectoId) }}" wire:navigate
               style="font-size:12px;color:var(--primary);">{{ __('reportes.panel_ver_informe') }}</a>
        </div>

        @forelse($porUsuario as $u)
            <div style="display:grid;grid-template-columns:minmax(90px,150px) 1fr 44px;align-items:center;gap:10px;
                        padding:5px 0;" title="{{ $u->name }} · {{ number_format($u->total) }}">
                <span style="font-size:12.5px;color:var(--text-secondary);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $u->name }}</span>
                <span style="display:block;height:9px;background:var(--bg-subtle);border-radius:4px;overflow:hidden;">
                    <span style="display:block;height:100%;border-radius:4px;background:var(--primary);
                                 width:{{ $maximo > 0 ? max(3, (int) round($u->total * 100 / $maximo)) : 0 }}%;"></span>
                </span>
                <span style="font-size:12.5px;font-weight:600;color:var(--text);text-align:right;font-variant-numeric:tabular-nums;">{{ number_format($u->total) }}</span>
            </div>
        @empty
            <div style="font-size:13px;color:var(--text-tertiary);padding:6px 0;">
                {{ __('reportes.panel_sin_gestiones') }}
            </div>
        @endforelse
    </div>
</div>
