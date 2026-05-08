<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">{{ $modo === 'edicion' ? 'Editar configuración' : 'Configurar proyecto' }}</h1>
            <div class="page-subtitle">
                <span class="font-mono" style="font-size:11px;color:var(--text-tertiary);">{{ $proyecto->codigo }}</span>
                <span style="margin:0 6px;color:var(--text-tertiary);">·</span>
                <span>{{ $proyecto->nombre }}</span>
                <span style="margin:0 6px;color:var(--text-tertiary);">·</span>
                <span class="font-mono" style="font-size:11px;color:var(--text-tertiary);text-transform:uppercase;">{{ $proyecto->tipo_operacion }}</span>
            </div>
        </div>
        <div style="display:flex;gap:8px;align-items:center;">
            <a href="{{ route('admin.proyectos') }}" wire:navigate class="btn btn-ghost btn-sm">
                <x-ui.icon name="arrow-left" :size="13" />
                <span>Volver</span>
            </a>
        </div>
    </div>

    @if(session('configurador-error'))
        <div class="alert alert-warning" style="margin-bottom:14px;">
            {{ session('configurador-error') }}
        </div>
    @endif

    <div style="display:grid;grid-template-columns:280px 1fr;gap:18px;align-items:start;">
        {{-- Panel izquierdo: stepper (wizard) o tabs (edicion) --}}
        <aside class="card card-pad" style="padding:14px;position:sticky;top:14px;">
            <div class="label-xs" style="margin-bottom:10px;">{{ $modo === 'edicion' ? 'Secciones' : 'Pasos' }}</div>

            @if($modo === 'wizard')
                @php
                    $porcentaje = $this->avance->porcentaje();
                    $estado     = $this->avance->estado();
                @endphp

                <div style="margin-bottom:14px;">
                    <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:6px;">
                        <span style="font-size:11px;color:var(--text-tertiary);text-transform:uppercase;letter-spacing:0.04em;">{{ $estado->etiqueta() }}</span>
                        <span style="font-size:13px;font-weight:600;color:var(--text);">{{ $porcentaje }}%</span>
                    </div>
                    <div style="height:6px;background:var(--bg-subtle);border-radius:999px;overflow:hidden;">
                        <div style="height:100%;background:var(--brand,#2563eb);width:{{ $porcentaje }}%;transition:width 200ms ease;"></div>
                    </div>
                </div>
            @endif

            <ol style="display:flex;flex-direction:column;gap:2px;list-style:none;padding:0;margin:0;">
                @foreach($pasos as $paso)
                    @php
                        $completo   = $this->avance->estaCompletado($paso);
                        $alcanzable = $modo === 'edicion' ? true : $this->avance->puedeSaltarA($paso);
                        $esActivo   = $paso === $pasoActivo;
                    @endphp

                    <li>
                        <button
                            type="button"
                            @if($alcanzable) wire:click="irAPaso('{{ $paso->value }}')" @else disabled @endif
                            class="sb-item"
                            style="
                                width:100%;
                                display:flex;align-items:center;gap:10px;
                                padding:8px 10px;border-radius:8px;border:0;text-align:left;cursor:{{ $alcanzable ? 'pointer' : 'not-allowed' }};
                                background:{{ $esActivo ? 'var(--bg-subtle)' : 'transparent' }};
                                color:{{ $alcanzable ? 'var(--text)' : 'var(--text-muted)' }};
                                font-weight:{{ $esActivo ? 600 : 400 }};
                            "
                        >
                            <span style="
                                width:22px;height:22px;flex-shrink:0;
                                border-radius:999px;
                                display:flex;align-items:center;justify-content:center;
                                font-size:11px;font-weight:600;
                                @if($completo)
                                    background:#15803d;color:#ffffff;
                                @elseif($esActivo && $modo === 'wizard')
                                    background:var(--brand,#2563eb);color:#ffffff;
                                @elseif($alcanzable)
                                    background:var(--bg-subtle);color:var(--text-secondary);border:1px solid var(--border);
                                @else
                                    background:var(--bg-subtle);color:var(--text-muted);
                                @endif
                            ">
                                @if($completo)
                                    <x-ui.icon name="check" :size="16" :stroke="3" style="color:#ffffff !important;" />
                                @elseif($modo === 'wizard' && ! $alcanzable)
                                    <x-ui.icon name="lock" :size="11" />
                                @elseif($modo === 'wizard')
                                    {{ $paso->indice() }}
                                @else
                                    ·
                                @endif
                            </span>

                            <span style="flex:1;min-width:0;font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                {{ $paso->etiqueta() }}
                            </span>

                            @if($paso->esOpcional())
                                <span style="font-size:10px;color:var(--text-tertiary);text-transform:uppercase;letter-spacing:0.04em;">Opcional</span>
                            @endif
                        </button>
                    </li>
                @endforeach
            </ol>
        </aside>

        {{-- Slot del paso activo --}}
        <section class="card card-pad" style="padding:24px;min-height:420px;display:flex;flex-direction:column;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;">
                <div>
                    @if($modo === 'wizard')
                        <div class="label-xs" style="margin-bottom:4px;">Paso {{ $pasoActivo->indice() }} de {{ count($pasos) }}</div>
                    @else
                        <div class="label-xs" style="margin-bottom:4px;">Sección</div>
                    @endif
                    <h2 style="font-size:18px;font-weight:600;color:var(--text);margin:0;">{{ $pasoActivo->etiqueta() }}</h2>
                </div>
                @if($modo === 'edicion')
                    @if($this->avance->estaCompleto())
                        <span class="badge badge-success" style="font-size:11px;">Configurado</span>
                    @else
                        <span class="badge badge-warning" style="font-size:11px;">Configuración parcial</span>
                    @endif
                @elseif($pasoActivo->esOpcional())
                    <span class="badge badge-ghost" style="font-size:11px;">Paso opcional</span>
                @endif
            </div>

            <div style="flex:1;">
                @switch($pasoActivo->value)
                    @case('datos_proyecto')
                        <livewire:tenancy.configurador-pasos.paso-datos-proyecto
                            :proyecto="$proyecto"
                            :key="'datos-'.$proyecto->id"/>
                        @break
                    @case('carteras')
                        <livewire:tenancy.configurador-pasos.paso-carteras
                            :proyecto="$proyecto"
                            :key="'carteras-'.$proyecto->id"/>
                        @break
                    @case('estados_caso')
                        <livewire:tenancy.configurador-pasos.paso-estados-caso
                            :proyecto="$proyecto"
                            :key="'estados-'.$proyecto->id"/>
                        @break
                    @case('tipos_gestion')
                        <livewire:tenancy.configurador-pasos.paso-tipos-gestion
                            :proyecto="$proyecto"
                            :key="'tipos-gestion-'.$proyecto->id"/>
                        @break
                    @case('resultados')
                        <livewire:tenancy.configurador-pasos.paso-resultados
                            :proyecto="$proyecto"
                            :key="'resultados-'.$proyecto->id"/>
                        @break
                    @case('motivos_no_contacto')
                        <livewire:tenancy.configurador-pasos.paso-motivos-no-contacto
                            :proyecto="$proyecto"
                            :key="'motivos-'.$proyecto->id"/>
                        @break
                    @case('catalogos_tipo')
                        <livewire:tenancy.configurador-pasos.paso-catalogos-tipo
                            :proyecto="$proyecto"
                            :key="'catalogos-tipo-'.$proyecto->id"/>
                        @break
                    @case('campos_personalizados')
                        <livewire:tenancy.configurador-pasos.paso-campos-personalizados
                            :proyecto="$proyecto"
                            :key="'campos-'.$proyecto->id"/>
                        @break
                    @case('resumen')
                        <livewire:tenancy.configurador-pasos.paso-resumen
                            :proyecto="$proyecto"
                            :modo="$modo"
                            :key="'resumen-'.$modo.'-'.$proyecto->id"/>
                        @break
                @endswitch
            </div>

            @if($modo === 'wizard')
                <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:18px;border-top:1px solid var(--border);padding-top:14px;">
                    <button type="button" wire:click="anterior" class="btn btn-ghost"
                        @if($pasoActivo->anterior() === null) disabled @endif>
                        <x-ui.icon name="arrow-left" :size="13" />
                        <span>Anterior</span>
                    </button>
                    <button type="button" wire:click="siguiente" class="btn btn-primary"
                        @if($pasoActivo->siguiente() === null || ! $this->avance->estaCompletado($pasoActivo)) disabled @endif>
                        <span>Siguiente</span>
                        <x-ui.icon name="arrow-right" :size="13" />
                    </button>
                </div>
            @endif
        </section>
    </div>
</div>
