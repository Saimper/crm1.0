<div>
    @if(session('paso-resumen-ok'))<div class="alert alert-success" style="margin-bottom:14px;">{{ session('paso-resumen-ok') }}</div>@endif
    @if(session('paso-resumen-error'))<div class="alert alert-warning" style="margin-bottom:14px;">{{ session('paso-resumen-error') }}</div>@endif

    <div class="card card-pad" style="margin-bottom:14px;">
        <div class="flex items-center justify-between gap-3.5">
            <div>
                <div class="label-xs" style="margin-bottom:4px;">{{ __('configurador.resumen.titulo') }}</div>
                <div class="font-semibold text-[18px] text-ink">{{ $proyecto->nombre }}</div>
                <div class="flex items-center gap-1.5" style="margin-top:4px;">
                    <span class="font-mono text-xs text-ink-500">{{ $proyecto->codigo }}</span>
                    <span class="text-ink-500">·</span>
                    <span class="font-mono text-xs text-ink-500 uppercase">{{ $proyecto->tipo_operacion }}</span>
                </div>
            </div>
            <div class="text-right">
                @if($estaCompleto)
                    <span class="badge badge-success" style="padding:6px 12px;">{{ __('configurador.resumen.configuracion_completa') }}</span>
                @else
                    <span class="badge badge-warning" style="padding:6px 12px;">{{ __('configurador.resumen.pasos_pendientes', ['n' => count($pasosPendientes)]) }}</span>
                @endif
            </div>
        </div>
    </div>

    <div class="card card-pad" style="padding:18px;margin-bottom:14px;">
        <div class="label-xs" style="margin-bottom:12px;">{{ __('configurador.resumen.pasos_wizard') }}</div>
        <ul class="flex flex-col gap-1.5" style="list-style:none;padding:0;margin:0;">
            @foreach($pasos as $paso)
                @php
                    $codigo = $paso->value;
                    $opcional = $paso->esOpcional();
                    $esResumen = $codigo === 'resumen';
                    $conteo = $conteos[$codigo] ?? null;
                    $completo = $esResumen
                        ? true
                        : ($codigo === 'datos_proyecto' || ($conteo !== null && $conteo > 0));
                @endphp
                <li class="flex items-center gap-2.5" style="padding:8px 10px;border-radius:8px;
                          background:{{ $completo ? 'rgba(22,163,74,0.06)' : 'var(--bg-subtle)' }};">
                    <span style="
                        width:22px;height:22px;border-radius:999px;display:inline-flex;
                        align-items:center;justify-content:center;font-size:11px;font-weight:600;
                        @if($completo) background:var(--success);color:var(--text-inverse);
                        @else background:var(--bg-subtle);color:var(--text-muted);border:1px solid var(--border);
                        @endif
                    ">
                        @if($completo)<x-ui.icon name="check" :size="15" :stroke="3" style="color:var(--text-inverse) !important;"/>@else{{ $paso->indice() }}@endif
                    </span>
                    <span class="flex-1 min-w-0 text-base text-ink">{{ $etiquetasPasos[$codigo] }}</span>
                    @if($opcional)
                        <span class="text-ink-500" style="font-size:10px;text-transform:uppercase;letter-spacing:0.04em;">{{ __('configurador.opcional') }}</span>
                    @endif
                    @if(! $esResumen && $codigo !== 'datos_proyecto' && $conteo !== null)
                        <span class="text-sm text-ink-600">
                            {{ $conteo }} {{ $conteo === 1 ? __('configurador.resumen.registro') : __('configurador.resumen.registros') }}
                        </span>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>

    @if(count($catalogosTipo) > 0)
        <div class="card card-pad" style="padding:18px;margin-bottom:14px;">
            <div class="label-xs" style="margin-bottom:12px;">{{ __('configurador.resumen.catalogos_tipo', ['tipo' => $proyecto->tipo_operacion]) }}</div>
            <ul class="flex flex-col gap-1.5" style="list-style:none;padding:0;margin:0;">
                @foreach($catalogosTipo as $cat)
                    <li class="flex items-center gap-2.5" style="padding:6px 10px;">
                        <span style="
                            width:14px;height:14px;border-radius:999px;
                            background:{{ $cat['conteo'] > 0 ? 'var(--success)' : 'var(--bg-subtle)' }};
                            border:1px solid {{ $cat['conteo'] > 0 ? 'var(--success)' : 'var(--border)' }};
                        "></span>
                        <span class="flex-1 min-w-0 text-base text-ink">{{ $cat['etiqueta'] }}</span>
                        <span class="text-sm text-ink-600">
                            {{ $cat['conteo'] }} {{ $cat['conteo'] === 1 ? __('configurador.resumen.registro') : __('configurador.resumen.registros') }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(($conteos['campos_personalizados'] ?? 0) === 0)
        <div class="alert alert-info text-sm" style="margin-bottom:14px;">
            {{ __('configurador.resumen.sin_campos_info') }}
        </div>
    @endif

    @if($modo === 'wizard')
        <div class="flex items-center justify-end gap-2" style="border-top:1px solid var(--border);padding-top:14px;margin-top:14px;">
            <button type="button" wire:click="volverAlInicio" class="btn btn-ghost">
                <x-ui.icon name="arrow-left" :size="13"/>
                <span>{{ __('configurador.resumen.volver_inicio') }}</span>
            </button>
            <button type="button" wire:click="finalizar" class="btn btn-primary"
                    @if(! $estaCompleto) disabled title="{{ __('configurador.resumen.faltan', ['pasos' => implode(', ', $pasosPendientes)]) }}" @endif>
                <span>{{ __('configurador.resumen.marcar_configurado') }}</span>
                <x-ui.icon name="check" :size="13"/>
            </button>
        </div>
    @endif
</div>
