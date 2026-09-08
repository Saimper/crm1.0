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


    {{-- Efectividad y dinero. Los dos son proporciones, así que los dos llevan la
         misma barra: una parte sobre un todo, no dos escalas distintas. --}}
    <div class="grid grid-cols-1 @if($puedeVerSupervision) sm:grid-cols-2 @endif gap-3">
        <div class="card" style="padding:16px;">
            <div style="display:flex;align-items:baseline;justify-content:space-between;gap:8px;">
                <span class="label-xs">{{ __('reportes.panel_efectividad') }}</span>
                <span style="font-size:11px;color:var(--text-tertiary);">
                    {{ __('reportes.panel_efectividad_pie', ['efectivas' => number_format($gestionesEfectivas), 'total' => number_format($totalGestiones)]) }}
                </span>
            </div>
            @if($efectividad === null)
                <div style="font-size:13px;color:var(--text-tertiary);margin-top:10px;">{{ __('reportes.panel_sin_gestiones') }}</div>
            @else
                <div style="font-size:26px;font-weight:600;line-height:1.15;margin-top:6px;font-variant-numeric:tabular-nums;color:var(--text);">{{ $efectividad }}%</div>
                <span style="display:block;height:9px;background:var(--bg-subtle);border-radius:4px;overflow:hidden;margin-top:10px;">
                    <span style="display:block;height:100%;border-radius:4px;background:var(--primary);width:{{ $efectividad }}%;"></span>
                </span>
            @endif
        </div>

        @if($puedeVerSupervision)
        <div class="card" style="padding:16px;">
            <div style="display:flex;align-items:baseline;justify-content:space-between;gap:8px;">
                <span class="label-xs">{{ __('reportes.panel_dinero') }}</span>
                <span style="font-size:11px;color:var(--text-tertiary);">{{ __('reportes.panel_dinero_nota') }}</span>
            </div>
            @if($dinero === null || ((float) $dinero->prometido <= 0 && (float) $dinero->cumplido <= 0))
                <div style="font-size:13px;color:var(--text-tertiary);margin-top:10px;">{{ __('reportes.panel_sin_promesas') }}</div>
            @else
                @php
                    // La barra mide lo RESUELTO en el periodo: de lo que se cerró,
                    // cuánto se cobró. Antes dividía cobrado entre prometido, que
                    // son poblaciones distintas —una por fecha de creación y otra
                    // por fecha de resolución— y podía pasar del 100 %.
                    $resuelto = (float) $dinero->cumplido + (float) $dinero->roto;
                    $pctCumplido = $resuelto > 0
                        ? (int) round(((float) $dinero->cumplido) * 100 / $resuelto)
                        : null;
                @endphp
                <div style="display:flex;align-items:baseline;gap:8px;margin-top:6px;">
                    <span style="font-size:22px;font-weight:600;font-variant-numeric:tabular-nums;color:var(--success-text);">{{ number_format((float) $dinero->cumplido, 2) }}</span>
                    <span style="font-size:13px;color:var(--text-tertiary);">{{ $dinero->moneda }} · {{ __('reportes.panel_dinero_cobrado') }}</span>
                </div>
                <div style="font-size:12px;color:var(--text-tertiary);margin-top:2px;font-variant-numeric:tabular-nums;">
                    {{ __('reportes.panel_dinero_prometido', ['monto' => number_format((float) $dinero->prometido, 2), 'moneda' => $dinero->moneda]) }}
                </div>
                @if($pctCumplido !== null)
                    <span style="display:block;height:9px;background:var(--bg-subtle);border-radius:4px;overflow:hidden;margin-top:10px;"
                          title="{{ __('reportes.panel_dinero_ratio', ['pct' => $pctCumplido]) }}">
                        <span style="display:block;height:100%;border-radius:4px;background:var(--success);width:{{ $pctCumplido }}%;"></span>
                    </span>
                    <div style="font-size:11px;color:var(--text-tertiary);margin-top:5px;">
                        {{ __('reportes.panel_dinero_ratio', ['pct' => $pctCumplido]) }}
                    </div>
                @endif
            @endif
        </div>
        @endif
    </div>

    {{-- Tendencia: un punto por día. Barras y no línea porque son conteos
         discretos y pocos; la línea insinuaría continuidad entre días. --}}
    @if($tendencia->isNotEmpty())
        <div class="card" style="padding:16px;">
            <div style="font-size:13px;font-weight:600;color:var(--text);margin-bottom:14px;">{{ __('reportes.panel_tendencia') }}</div>
            <div style="display:flex;align-items:flex-end;gap:4px;height:76px;">
                @foreach($tendencia as $d)
                    {{-- Ancho acotado: con pocos días, un flex:1 suelto convierte cada
                         barra en un bloque y la tendencia deja de leerse como tal. --}}
                    <div style="flex:1;max-width:26px;display:flex;flex-direction:column;justify-content:flex-end;height:100%;"
                         title="{{ $d->etiqueta }} · {{ number_format($d->total) }}">
                        <span style="display:block;border-radius:4px 4px 0 0;
                                     background:{{ $d->total > 0 ? 'var(--primary)' : 'var(--border)' }};
                                     height:{{ $maximoDia > 0 && $d->total > 0 ? max(6, (int) round($d->total * 100 / $maximoDia)) : 3 }}%;"></span>
                    </div>
                @endforeach
            </div>
            <div style="display:flex;justify-content:space-between;margin-top:6px;font-size:11px;color:var(--text-tertiary);">
                <span>{{ $tendencia->first()->etiqueta }}</span>
                <span>{{ $tendencia->last()->etiqueta }}</span>
            </div>
        </div>
    @endif

    @if($puedeVerSupervision)
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
@endif
</div>
