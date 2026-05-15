<div>
    @if(session('paso-resumen-ok'))<div class="alert alert-success" style="margin-bottom:14px;">{{ session('paso-resumen-ok') }}</div>@endif
    @if(session('paso-resumen-error'))<div class="alert alert-warning" style="margin-bottom:14px;">{{ session('paso-resumen-error') }}</div>@endif

    <div class="card card-pad" style="padding:20px;margin-bottom:14px;">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:14px;">
            <div>
                <div class="label-xs" style="margin-bottom:4px;">Resumen de configuración</div>
                <div style="font-size:18px;font-weight:600;color:var(--text);">{{ $proyecto->nombre }}</div>
                <div style="display:flex;gap:6px;align-items:center;margin-top:4px;">
                    <span class="font-mono" style="font-size:11px;color:var(--text-tertiary);">{{ $proyecto->codigo }}</span>
                    <span style="color:var(--text-tertiary);">·</span>
                    <span class="font-mono" style="font-size:11px;color:var(--text-tertiary);text-transform:uppercase;">{{ $proyecto->tipo_operacion }}</span>
                </div>
            </div>
            <div style="text-align:right;">
                @if($estaCompleto)
                    <span class="badge badge-success" style="padding:6px 12px;font-size:12px;">Configuración completa</span>
                @else
                    <span class="badge badge-warning" style="padding:6px 12px;font-size:12px;">{{ count($pasosPendientes) }} paso(s) pendiente(s)</span>
                @endif
            </div>
        </div>
    </div>

    <div class="card card-pad" style="padding:18px;margin-bottom:14px;">
        <div class="label-xs" style="margin-bottom:12px;">Pasos del wizard</div>
        <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:6px;">
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
                <li style="display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:8px;
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
                    <span style="flex:1;font-size:13px;color:var(--text);">{{ $etiquetasPasos[$codigo] }}</span>
                    @if($opcional)
                        <span style="font-size:10px;color:var(--text-tertiary);text-transform:uppercase;letter-spacing:0.04em;">Opcional</span>
                    @endif
                    @if(! $esResumen && $codigo !== 'datos_proyecto' && $conteo !== null)
                        <span style="font-size:12px;color:var(--text-secondary);">
                            {{ $conteo }} {{ $conteo === 1 ? 'registro' : 'registros' }}
                        </span>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>

    @if(count($catalogosTipo) > 0)
        <div class="card card-pad" style="padding:18px;margin-bottom:14px;">
            <div class="label-xs" style="margin-bottom:12px;">Catálogos del tipo {{ $proyecto->tipo_operacion }}</div>
            <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:6px;">
                @foreach($catalogosTipo as $cat)
                    <li style="display:flex;align-items:center;gap:10px;padding:6px 10px;">
                        <span style="
                            width:14px;height:14px;border-radius:999px;
                            background:{{ $cat['conteo'] > 0 ? 'var(--success)' : 'var(--bg-subtle)' }};
                            border:1px solid {{ $cat['conteo'] > 0 ? 'var(--success)' : 'var(--border)' }};
                        "></span>
                        <span style="flex:1;font-size:13px;color:var(--text);">{{ $cat['etiqueta'] }}</span>
                        <span style="font-size:12px;color:var(--text-secondary);">
                            {{ $cat['conteo'] }} {{ $cat['conteo'] === 1 ? 'registro' : 'registros' }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(($conteos['campos_personalizados'] ?? 0) === 0)
        <div class="alert alert-info" style="margin-bottom:14px;font-size:12px;">
            Sin campos personalizados configurados. Puedes crearlos más adelante desde la configuración.
        </div>
    @endif

    @if($modo === 'wizard')
        <div style="display:flex;justify-content:flex-end;gap:8px;border-top:1px solid var(--border);padding-top:14px;margin-top:14px;">
            <button type="button" wire:click="volverAlInicio" class="btn btn-ghost">
                <x-ui.icon name="arrow-left" :size="13"/>
                <span>Volver al inicio del wizard</span>
            </button>
            <button type="button" wire:click="finalizar" class="btn btn-primary"
                    @if(! $estaCompleto) disabled title="Faltan: {{ implode(', ', $pasosPendientes) }}" @endif>
                <span>Marcar proyecto como configurado</span>
                <x-ui.icon name="check" :size="13"/>
            </button>
        </div>
    @endif
</div>
