<div class="page" style="padding-top:16px;">

    {{-- Compromiso vigente alert (full-width arriba) --}}
    @if($compromisoActivo)
        <x-ui.alert tone="success" style="margin-bottom:16px;">
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                <div>
                    <div style="font-size:11px;text-transform:uppercase;letter-spacing:0.06em;font-weight:600;color:var(--success-text);">
                        {{ __('casos.active_commitment') }}
                    </div>
                    <div style="font-size:14px;color:var(--text);font-weight:500;">
                        {{ __('casos.expires', ['date' => \Illuminate\Support\Carbon::parse($compromisoActivo->fecha_vencimiento)->format('d/m/Y')]) }}
                        @if(isset($compromisoActivo->promesa) && $compromisoActivo->promesa)
                            · <span class="font-mono">{{ $compromisoActivo->promesa->moneda }} {{ number_format((float) $compromisoActivo->promesa->monto, 2, '.', ',') }}</span>
                        @elseif(isset($compromisoActivo->cierre) && $compromisoActivo->cierre)
                            · <span class="font-mono">{{ $compromisoActivo->cierre->moneda }} {{ number_format((float) $compromisoActivo->cierre->monto_cierre, 2, '.', ',') }}</span>
                            @if($compromisoActivo->cierre->etapa_nombre) · {{ $compromisoActivo->cierre->etapa_nombre }} @endif
                        @elseif(isset($compromisoActivo->resolucion) && $compromisoActivo->resolucion)
                            · {{ $compromisoActivo->resolucion->accion_comprometida }}
                            @if($compromisoActivo->resolucion->escalamiento_nombre) · {{ $compromisoActivo->resolucion->escalamiento_nombre }} @endif
                        @elseif(isset($compromisoActivo->accion) && $compromisoActivo->accion)
                            · {{ $compromisoActivo->accion->descripcion_accion }}
                            @if($compromisoActivo->accion->tipo_accion_nombre) · {{ $compromisoActivo->accion->tipo_accion_nombre }} @endif
                            @if($compromisoActivo->accion->tecnico_asignado) · {{ $compromisoActivo->accion->tecnico_asignado }} @endif
                        @endif
                    </div>
                </div>
                <div class="alert-actions">
                    @can('compromisos.crear', $proyectoActivo->id)
                        <a href="{{ route('proyectos.compromisos.editar', ['proyecto_id' => $proyectoActivo->id, 'compromiso' => $compromisoActivo->public_id]) }}"
                           wire:navigate class="btn btn-ghost btn-sm" style="text-decoration:none;">
                            <x-ui.icon name="edit" :size="13" />
                            <span>{{ __('common.edit') }}</span>
                        </a>
                    @endcan
                    @if($casoActivo && $casoActivo->tipo_caso === 'cobranza' && $compromisoActivo->tipo_compromiso === 'promesa_pago')
                        <livewire:cobranza.resolver-promesa :compromisoId="$compromisoActivo->id" :key="'resolver-promesa-'.$compromisoActivo->id" />
                    @elseif($casoActivo && $casoActivo->tipo_caso === 'ticket_cx' && $compromisoActivo->tipo_compromiso === 'resolucion_ticket')
                        <livewire:cx.resolver-resolucion :compromisoId="$compromisoActivo->id" :key="'resolver-resolucion-'.$compromisoActivo->id" />
                    @elseif($casoActivo && $casoActivo->tipo_caso === 'lead_venta' && $compromisoActivo->tipo_compromiso === 'cierre_venta')
                        <livewire:venta.resolver-cierre :compromisoId="$compromisoActivo->id" :key="'resolver-cierre-'.$compromisoActivo->id" />
                    @elseif($casoActivo && $casoActivo->tipo_caso === 'servicio' && $compromisoActivo->tipo_compromiso === 'accion_servicio')
                        <livewire:servicio.resolver-accion :compromisoId="$compromisoActivo->id" :key="'resolver-accion-'.$compromisoActivo->id" />
                    @endif
                </div>
            </div>
        </x-ui.alert>
    @endif

    {{-- Grid 3 columnas: identidad+caso | form gestión | historial --}}
    <div class="vt-grid">

        {{-- Col izquierda: identidad + selector casos + datos caso --}}
        <div class="vt-col-left">
            <x-ui.card>
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;">
                    <div style="min-width:0;">
                        <div class="label-xs">
                            {{ $persona->tipo_identificacion_codigo ?? 'ID' }}
                            · <span class="font-mono">{{ $persona->identificacion }}</span>
                        </div>
                        <h2 style="font-size:18px;font-weight:600;color:var(--text);margin-top:4px;line-height:1.25;">
                            {{ $nombrePersona !== '' ? $nombrePersona : '—' }}
                        </h2>
                        <div style="font-size:11px;color:var(--text-tertiary);margin-top:4px;">
                            {{ ucfirst($persona->tipo_persona) }}
                            @if($persona->tipo_persona === 'fisica' && $persona->fecha_nacimiento)
                                · {{ __('casos.born_abbrev') }} {{ \Illuminate\Support\Carbon::parse($persona->fecha_nacimiento)->format('d/m/Y') }}
                            @endif
                        </div>
                    </div>
                    <div style="display:flex;gap:6px;">
                        @can('personas.editar', $proyectoActivo->id)
                            <a href="{{ route('proyectos.personas.editar', ['proyecto_id' => $proyectoActivo->id, 'persona' => $persona->public_id]) }}"
                               wire:navigate class="btn btn-ghost btn-sm" style="text-decoration:none;">
                                <x-ui.icon name="edit" :size="14" />
                                <span>{{ __('common.edit') }}</span>
                            </a>
                        @endcan
                        <a href="{{ route('proyectos.personas.contactos', ['proyecto_id' => $proyectoActivo->id, 'persona' => $persona->public_id]) }}"
                           wire:navigate class="btn btn-ghost btn-sm" style="text-decoration:none;">
                            <x-ui.icon name="phone" :size="14" />
                            <span>{{ __('casos.contacts_button') }}</span>
                        </a>
                    </div>
                </div>

                @if($contactos->isNotEmpty())
                    <div style="margin-top:12px;display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:6px;">
                        @foreach($contactos as $c)
                            <div style="border:1px solid var(--border);border-radius:6px;padding:6px 8px;">
                                <div style="display:flex;align-items:center;justify-content:space-between;gap:6px;">
                                    <span style="font-size:11px;font-weight:500;color:var(--text);">{{ ucfirst($c->tipo) }}</span>
                                    @if($c->es_principal)
                                        <span style="font-size:9px;text-transform:uppercase;color:var(--primary);font-weight:600;">{{ __('contactos.badge_principal') }}</span>
                                    @endif
                                </div>
                                <div style="font-size:12px;color:var(--text-secondary);word-break:break-all;">{{ $c->valor }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-ui.card>

            <x-ui.card :title="__('casos.cases_count', ['count' => $casos->count()])" style="margin-top:12px;">
                @can('casos.crear', $proyectoActivo->id)
                    <a href="{{ route('proyectos.casos.crear', ['proyecto_id' => $proyectoActivo->id, 'persona' => $persona->public_id]) }}"
                       wire:navigate class="btn btn-primary btn-sm" style="margin-bottom:8px;text-decoration:none;">
                        <x-ui.icon name="plus" :size="13" />
                        <span>{{ __('casos.new_case') }}</span>
                    </a>
                @endcan
                @if($casos->isEmpty())
                    <x-ui.empty-state :title="__('casos.no_open_cases')" :message="__('casos.no_open_cases_desc')" />
                @else
                    <div style="display:flex;flex-direction:column;gap:6px;margin:-4px -4px 0;">
                        @foreach($casos as $c)
                            @php
                                $activo = $casoActivo && $c->public_id === $casoActivo->public_id;
                                $tipoTone = match ($c->tipo_caso) {
                                    'cobranza'   => 'warning',
                                    'ticket_cx'  => 'info',
                                    'lead_venta' => 'success',
                                    'servicio'   => 'primary',
                                    default      => 'neutral',
                                };
                            @endphp
                            <button type="button" wire:click="seleccionarCaso('{{ $c->public_id }}')"
                                    style="text-align:left;padding:10px 8px;border-radius:6px;background:{{ $activo ? 'var(--primary-soft)' : 'transparent' }};border:1px solid {{ $activo ? 'var(--primary-soft-border)' : 'transparent' }};display:flex;align-items:center;gap:10px;cursor:pointer;width:100%;">
                                <x-ui.badge :tone="$tipoTone">{{ ucfirst(str_replace('_', ' ', $c->tipo_caso)) }}</x-ui.badge>
                                <div style="flex:1;min-width:0;">
                                    <div style="font-size:13px;font-weight:500;color:var(--text);">{{ $c->cartera_nombre }}</div>
                                    <div style="font-size:11px;color:var(--text-tertiary);">
                                        {{ $c->estado_caso_nombre }}
                                        @if($c->tiene_compromiso_vigente)
                                            · <span style="color:var(--success);font-weight:500;">{{ __('casos.active_commitment_label') }}</span>
                                        @endif
                                    </div>
                                </div>
                                <div style="font-size:11px;color:var(--text-tertiary);text-align:right;">
                                    {{ __('casos.prio_label', ['value' => $c->prioridad]) }}
                                </div>
                            </button>
                        @endforeach
                    </div>
                @endif
            </x-ui.card>

            @if($casoActivo)
                {{-- Detalle del caso (panel tipo-específico) — ahora arriba del historial --}}
                <div style="margin-top:12px;">
                    @if($casoActivo->tipo_caso === 'cobranza')
                        @include('cobranza::partials.panel-caso', ['cobranza' => $casoCobranza])
                    @elseif($casoActivo->tipo_caso === 'ticket_cx')
                        @include('cx::partials.panel-caso', ['ticket' => $casoTicketCx])
                    @elseif($casoActivo->tipo_caso === 'lead_venta')
                        @include('venta::partials.panel-caso', ['lead' => $casoLeadVenta])
                    @elseif($casoActivo->tipo_caso === 'servicio')
                        @include('servicio::partials.panel-caso', ['servicio' => $casoServicio])
                    @endif
                    @can('casos.editar', $proyectoActivo->id)
                        <div style="margin-top:8px;text-align:right;">
                            <a href="{{ route('proyectos.casos.editar', ['proyecto_id' => $proyectoActivo->id, 'caso' => $casoActivo->public_id]) }}"
                               wire:navigate class="btn btn-ghost btn-sm" style="text-decoration:none;">
                                <x-ui.icon name="edit" :size="13" />
                                <span>{{ __('casos.edit_case') }}</span>
                            </a>
                        </div>
                    @endcan
                </div>

                @if(isset($compromisosResueltos) && $compromisosResueltos->isNotEmpty())
                    <x-ui.card :title="__('casos.resolved_commitments', ['count' => $compromisosResueltos->count()])" style="margin-top:12px;">
                        <ul style="display:flex;flex-direction:column;gap:6px;font-size:12px;">
                            @foreach($compromisosResueltos as $c)
                                @php
                                    $estadoTone = match ($c->estado) {
                                        'cumplido' => 'success',
                                        'roto' => 'danger',
                                        'cancelado' => 'neutral',
                                        default => 'neutral',
                                    };
                                @endphp
                                <li style="display:flex;align-items:center;justify-content:space-between;gap:8px;padding:6px 8px;border:1px solid var(--border);border-radius:6px;">
                                    <div style="min-width:0;">
                                        <div style="display:flex;align-items:center;gap:6px;">
                                            <x-ui.badge :tone="$estadoTone" size="sm">{{ ucfirst($c->estado) }}</x-ui.badge>
                                            <span style="font-size:11px;color:var(--text-tertiary);">
                                                {{ str_replace('_', ' ', $c->tipo_compromiso) }}
                                            </span>
                                        </div>
                                        <div style="font-size:11px;color:var(--text-tertiary);margin-top:2px;">
                                            {{ __('casos.expiry_label', ['date' => \Illuminate\Support\Carbon::parse($c->fecha_vencimiento)->format('d/m/Y')]) }}
                                        </div>
                                    </div>
                                    <div style="font-size:11px;color:var(--text-secondary);text-align:right;">
                                        @if($c->fecha_resolucion)
                                            {{ __('casos.resolved_label') }}<br>
                                            <span class="font-mono">{{ \Illuminate\Support\Carbon::parse($c->fecha_resolucion)->format('d/m/Y') }}</span>
                                        @else
                                            <span style="color:var(--text-tertiary);">{{ __('casos.no_date') }}</span>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </x-ui.card>
                @endif

                @can('entidades.ver', $proyectoActivo->id)
                    <livewire:entidades.panel-vinculadas
                        :proyectoId="(int) $proyectoActivo->id"
                        vinculo="caso"
                        :vinculoId="(int) $casoActivo->id"
                        :carteraId="(int) ($casoActivo->cartera_id ?? 0) ?: null"
                        :key="'panel-ent-caso-'.$casoActivo->id" />
                    <livewire:entidades.panel-vinculadas
                        :proyectoId="(int) $proyectoActivo->id"
                        vinculo="persona"
                        :vinculoId="(int) $persona->id"
                        :carteraId="(int) ($casoActivo->cartera_id ?? 0) ?: null"
                        :key="'panel-ent-persona-'.$persona->id" />
                @endcan
            @endif
        </div>

        {{-- Col centro: formulario nueva gestión. Los campos personalizados del CASO
             se editan en "Editar caso"; aquí los del ámbito gestión van inline en NuevaGestion. --}}
        <div class="vt-col-mid">
            @if($casoActivo)
                <x-ui.card :title="__('casos.register_gestion_title')">
                    <livewire:casos.nueva-gestion
                        :casoId="$casoActivo->id"
                        :personaId="$persona->id"
                        :tipoCaso="$casoActivo->tipo_caso"
                        :key="'nueva-gestion-'.$casoActivo->id" />
                </x-ui.card>
            @else
                <x-ui.card>
                    <x-ui.empty-state :title="__('casos.select_case_title')" :message="__('casos.select_case_desc')" />
                </x-ui.card>
            @endif
        </div>

        {{-- Col derecha: historial --}}
        <div class="vt-col-right">
            @if($casoActivo)
                <x-ui.card :title="__('casos.history_title', ['count' => $historial->count()])">
                    @if($historial->isEmpty())
                        <x-ui.empty-state :title="__('casos.no_gestions')" :message="__('casos.no_gestions_desc')" />
                    @else
                        <x-ui.timeline>
                            @foreach($historial as $g)
                                @php
                                    $tone = match (mb_strtolower((string) $g->resultado_nombre)) {
                                        'contacto efectivo', 'promesa de pago', 'venta cerrada', 'resuelto' => 'success',
                                        'sin contacto', 'no contesta'                                       => 'warning',
                                        'rechazo', 'cancelado'                                              => 'danger',
                                        default                                                             => 'neutral',
                                    };
                                @endphp
                                <x-ui.timeline-item
                                    :tone="$tone"
                                    :timestamp="\Illuminate\Support\Carbon::parse($g->creada_en)->format('d/m/Y H:i')"
                                    :title="($g->resultado_nombre ?? '—') . ' · ' . ($g->tipo_gestion_nombre ?? '—')">
                                    @if($g->notas)
                                        <div style="margin-bottom:4px;">{{ $g->notas }}</div>
                                    @endif
                                    <div style="font-size:11px;color:var(--text-tertiary);">
                                        {{ $g->canal_nombre ?? '—' }}
                                        · {{ $g->usuario_nombre ?? '—' }}
                                        @if($g->duracion_segundos)
                                            · {{ (int) floor($g->duracion_segundos / 60) }}m {{ $g->duracion_segundos % 60 }}s
                                        @endif
                                    </div>
                                    @if($g->motivo_no_contacto_nombre || $g->causa_nombre)
                                        <div style="margin-top:4px;display:flex;flex-wrap:wrap;gap:4px;">
                                            @if($g->motivo_no_contacto_nombre)
                                                <x-ui.badge tone="warning" size="sm">{{ __('casos.no_contact_badge', ['motivo' => $g->motivo_no_contacto_nombre]) }}</x-ui.badge>
                                            @endif
                                            @if($g->causa_nombre)
                                                <x-ui.badge tone="info" size="sm">{{ __('casos.cause_badge', ['causa' => $g->causa_nombre]) }}</x-ui.badge>
                                            @endif
                                        </div>
                                    @endif

                                    {{-- Valores de campos personalizados ámbito gestión × tipo_gestion. --}}
                                    @if(! empty($valoresCamposGestion[$g->id] ?? []))
                                        <dl style="margin-top:6px;padding-top:6px;border-top:1px dashed var(--border);display:grid;grid-template-columns:auto 1fr;gap:2px 8px;font-size:11px;">
                                            @foreach($valoresCamposGestion[$g->id] as $cp)
                                                <dt style="color:var(--text-tertiary);">{{ $cp['etiqueta'] }}</dt>
                                                <dd style="color:var(--text);margin:0;">{{ $cp['valor'] }}</dd>
                                            @endforeach
                                        </dl>
                                    @endif
                                </x-ui.timeline-item>
                            @endforeach
                        </x-ui.timeline>
                    @endif
                </x-ui.card>
            @else
                <x-ui.card :title="__('casos.custom_fields_panel')">
                    <x-ui.empty-state :title="__('casos.no_active_case')" :message="__('casos.no_active_case_desc')" />
                </x-ui.card>
            @endif
        </div>
    </div>
</div>
