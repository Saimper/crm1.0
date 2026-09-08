<div class="flex flex-col" style="gap:14px;">

    {{-- Filtro de periodo: una sola fila encima de todo, como manda el patrón. --}}
    <div class="gap-3 flex items-baseline justify-between flex-wrap">
        <div>
            <div class="font-semibold text-[15px] text-ink">{{ __('reportes.panel_titulo') }}</div>
            <div class="text-sm text-ink-500">{{ $etiquetaRango }}</div>
        </div>
        <div style="display:inline-flex;border:1px solid var(--border);border-radius:8px;overflow:hidden;">
            @foreach(['hoy','semana','mes'] as $r)
                <button type="button" wire:click="cambiarRango('{{ $r }}')"
                        class="text-sm"
                        style="padding:5px 12px;border:0;cursor:pointer;
                               background:{{ $rango === $r ? 'var(--primary-soft)' : 'transparent' }};
                               color:{{ $rango === $r ? 'var(--primary)' : 'var(--text-secondary)' }};
                               font-weight:{{ $rango === $r ? '600' : '400' }};">
                    {{ __('reportes.panel_rango_'.$r) }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- Las cifras de antes se quedan en pantalla mientras llega el periodo
         nuevo; sólo esta barra dice que se está trabajando. --}}
    <x-ui.cargando />

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
                <div class="flex items-center" style="gap:6px;">
                    @if($t['punto'])
                        <span style="width:7px;height:7px;border-radius:50%;background:{{ $t['punto'] }};flex-shrink:0;"></span>
                    @endif
                    <span class="label-xs">{{ __('reportes.panel_'.$t['clave']) }}</span>
                </div>
                <div class="font-semibold tnum text-5xl" style="margin-top:6px;color:{{ $t['color'] }};">
                    {{ number_format($t['valor']) }}
                </div>
            </div>
        @endforeach
    </div>

    {{-- Vencidas y sin resolver: no es un cuarto estado, es una alerta. Solo
         aparece cuando hay algo que atender, para no ocupar sitio en vano. --}}
    @if($vencidasSinResolver > 0)
        <div class="card flex items-center" style="padding:12px 14px;gap:10px;
                                     border-color:var(--warning);background:var(--warning-soft);">
            <x-ui.icon name="alert-triangle" :size="15" class="text-warning-700" style="flex-shrink:0;" />
            <span class="text-base text-warning-700">
                {{ trans_choice('reportes.panel_vencidas', $vencidasSinResolver, ['n' => number_format($vencidasSinResolver)]) }}
            </span>
        </div>
    @endif


    {{-- Efectividad y dinero. Los dos son proporciones, así que los dos llevan la
         misma barra: una parte sobre un todo, no dos escalas distintas. --}}
    <div class="grid grid-cols-1 @if($puedeVerSupervision) sm:grid-cols-2 @endif gap-3">
        <div class="card card-pad-sm">
            <div class="gap-2 flex items-baseline justify-between">
                <span class="label-xs">{{ __('reportes.panel_efectividad') }}</span>
                <span class="text-xs text-ink-500">
                    {{ __('reportes.panel_efectividad_pie', ['efectivas' => number_format($gestionesEfectivas), 'total' => number_format($totalGestiones)]) }}
                </span>
            </div>
            @if($efectividad === null)
                <div class="text-base text-ink-500" style="margin-top:10px;">{{ __('reportes.panel_sin_gestiones') }}</div>
            @else
                <div class="font-semibold tnum text-5xl text-ink" style="margin-top:6px;">{{ $efectividad }}%</div>
                <span class="bar-track" style="margin-top:10px;">
                    <span class="bar-fill" style="width:{{ $efectividad }}%;"></span>
                </span>
            @endif
        </div>

        @if($puedeVerSupervision)
        <div class="card card-pad-sm">
            <div class="gap-2 flex items-baseline justify-between">
                <span class="label-xs">{{ __('reportes.panel_dinero') }}</span>
                <span class="text-xs text-ink-500">{{ __('reportes.panel_dinero_nota') }}</span>
            </div>
            @if($dinero === null || ((float) $dinero->prometido <= 0 && (float) $dinero->cumplido <= 0))
                <div class="text-base text-ink-500" style="margin-top:10px;">{{ __('reportes.panel_sin_promesas') }}</div>
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
                <div class="gap-2 flex items-baseline" style="margin-top:6px;">
                    <span class="font-semibold tnum text-success-700 text-[22px]">{{ number_format((float) $dinero->cumplido, 2) }}</span>
                    <span class="text-base text-ink-500">{{ $dinero->moneda }} · {{ __('reportes.panel_dinero_cobrado') }}</span>
                </div>
                <div class="text-sm text-ink-500 tnum" style="margin-top:2px;">
                    {{ __('reportes.panel_dinero_prometido', ['monto' => number_format((float) $dinero->prometido, 2), 'moneda' => $dinero->moneda]) }}
                </div>
                @if($pctCumplido !== null)
                    <span class="bar-track" style="margin-top:10px;"
                          title="{{ __('reportes.panel_dinero_ratio', ['pct' => $pctCumplido]) }}">
                        <span class="bar-fill" style="background:var(--success);width:{{ $pctCumplido }}%;"></span>
                    </span>
                    <div class="text-xs text-ink-500" style="margin-top:5px;">
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
        <div class="card card-pad-sm">
            <div class="text-base font-semibold text-ink" style="margin-bottom:14px;">{{ __('reportes.panel_tendencia') }}</div>
            <div class="gap-1 flex items-end" style="height:76px;">
                @foreach($tendencia as $d)
                    {{-- Ancho acotado: con pocos días, un flex:1 suelto convierte cada
                         barra en un bloque y la tendencia deja de leerse como tal. --}}
                    <div class="flex flex-col flex-1 justify-end" style="max-width:26px;height:100%;"
                         title="{{ $d->etiqueta }} · {{ number_format($d->total) }}">
                        <span style="display:block;border-radius:4px 4px 0 0;
                                     background:{{ $d->total > 0 ? 'var(--primary)' : 'var(--border)' }};
                                     height:{{ $maximoDia > 0 && $d->total > 0 ? max(6, (int) round($d->total * 100 / $maximoDia)) : 3 }}%;"></span>
                    </div>
                @endforeach
            </div>
            <div class="text-xs text-ink-500 flex justify-between" style="margin-top:6px;">
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
    <div class="card card-pad-sm">
        <div class="flex items-baseline justify-between" style="margin-bottom:14px;">
            <div class="text-base font-semibold text-ink">{{ __('reportes.panel_por_usuario') }}</div>
            <a href="{{ route('proyectos.reportes.operativos', $proyectoId) }}" wire:navigate
               class="text-sm text-brand-500">{{ __('reportes.panel_ver_informe') }}</a>
        </div>

        @forelse($porUsuario as $u)
            <div class="items-center" style="display:grid;grid-template-columns:minmax(90px,150px) 1fr 44px;gap:10px;
                        padding:5px 0;" title="{{ $u->name }} · {{ number_format($u->total) }}">
                <span class="text-[12.5px] text-ink-600" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $u->name }}</span>
                <span class="bar-track">
                    <span class="bar-fill" style="
                                 width:{{ $maximo > 0 ? max(3, (int) round($u->total * 100 / $maximo)) : 0 }}%;"></span>
                </span>
                <span class="text-[12.5px] font-semibold tnum text-ink text-right">{{ number_format($u->total) }}</span>
            </div>
        @empty
            <div class="text-base text-ink-500" style="padding:6px 0;">
                {{ __('reportes.panel_sin_gestiones') }}
            </div>
        @endforelse
    </div>
@endif
</div>
