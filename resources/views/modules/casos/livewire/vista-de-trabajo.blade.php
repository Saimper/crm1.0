<div class="page" style="padding-top:16px;">

    @if($compromisosPendientes->isNotEmpty())
        <x-ui.card title="Compromisos pendientes de esta persona" class="mb-4">
            <div class="overflow-x-auto">
                <table class="table table-compact">
                    <thead><tr><th>Cuenta y cartera</th><th>Compromiso</th><th>Vencimiento</th><th>Importe / detalle</th><th></th></tr></thead>
                    <tbody>
                        @foreach($compromisosPendientes as $pendiente)
                            @php $cuentaCompromiso = $casos->firstWhere('id', $pendiente->caso_id); @endphp
                            <tr wire:key="pendiente-{{ $pendiente->id }}">
                                <td>
                                    <button type="button" wire:click="seleccionarCaso('{{ $cuentaCompromiso->public_id }}')" class="font-mono text-sm text-brand-500">{{ $cuentaCompromiso->referencia }}</button>
                                    <div class="text-xs text-ink-500">{{ $cuentaCompromiso->cartera_nombre }}</div>
                                </td>
                                <td>{{ match($pendiente->tipo_compromiso) { 'promesa_pago' => 'Promesa de pago', 'cierre_venta' => 'Cierre de venta', 'resolucion_ticket' => 'Resolución', default => 'Acción de servicio' } }}</td>
                                <td class="font-mono">{{ fecha_local($pendiente->fecha_vencimiento) }}</td>
                                <td class="font-mono">@if($pendiente->monto !== null){{ $pendiente->moneda }} {{ numero_local($pendiente->monto) }}@else{{ $pendiente->detalle ?? '—' }}@endif</td>
                                <td>
                                    @if($casoActivo && (int) $casoActivo->id === (int) $pendiente->caso_id && $puedeGestionarCaso)
                                        @can('compromisos.crear', $proyectoActivo->id)
                                            <a href="{{ route('proyectos.compromisos.editar', ['proyecto_id' => $proyectoActivo->id, 'compromiso' => $pendiente->public_id]) }}" wire:navigate class="btn btn-ghost btn-sm">Editar</a>
                                        @endcan
                                        @if($pendiente->tipo_compromiso === 'promesa_pago')
                                            <livewire:cobranza.resolver-promesa :compromisoId="$pendiente->id" :key="'resolver-promesa-'.$pendiente->id" />
                                        @elseif($pendiente->tipo_compromiso === 'resolucion_ticket')
                                            <livewire:cx.resolver-resolucion :compromisoId="$pendiente->id" :key="'resolver-resolucion-'.$pendiente->id" />
                                        @elseif($pendiente->tipo_compromiso === 'cierre_venta')
                                            <livewire:venta.resolver-cierre :compromisoId="$pendiente->id" :key="'resolver-cierre-'.$pendiente->id" />
                                        @elseif($pendiente->tipo_compromiso === 'accion_servicio')
                                            <livewire:servicio.resolver-accion :compromisoId="$pendiente->id" :key="'resolver-accion-'.$pendiente->id" />
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-ui.card>
    @endif

    {{-- Grid 3 columnas: identidad+caso | form gestión | historial --}}
    <div class="vt-grid">

        {{-- Col izquierda: identidad + selector casos + datos caso --}}
        <div class="vt-col-left">
            <x-ui.card>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="label-xs">
                            {{ $persona->tipo_identificacion_codigo ?? 'ID' }}
                            · <span class="font-mono">{{ $persona->identificacion }}</span>
                        </div>
                        <h2 class="font-semibold text-ink" style="font-size:18px;margin-top:4px;line-height:1.25;">
                            {{ $nombrePersona !== '' ? $nombrePersona : '—' }}
                        </h2>
                        <div class="text-xs text-ink-500" style="margin-top:4px;">
                            {{ ucfirst($persona->tipo_persona) }}
                            @if($persona->tipo_persona === 'fisica' && $persona->fecha_nacimiento)
                                · {{ __('casos.born_abbrev') }} {{ fecha_local($persona->fecha_nacimiento) }}
                            @endif
                        </div>
                    </div>
                    <div class="flex" style="gap:6px;">
                        @can('personas.editar', $proyectoActivo->id)
                            <a href="{{ route('proyectos.personas.editar', ['proyecto_id' => $proyectoActivo->id, 'persona' => $persona->public_id]) }}"
                               wire:navigate class="btn btn-ghost btn-sm">
                                <x-ui.icon name="edit" :size="14" />
                                <span>{{ __('common.edit') }}</span>
                            </a>
                        @endcan
                        @can('contactos.ver', $proyectoActivo->id)
                        <a href="{{ route('proyectos.personas.contactos', ['proyecto_id' => $proyectoActivo->id, 'persona' => $persona->public_id]) }}"
                           wire:navigate class="btn btn-ghost btn-sm">
                            <x-ui.icon name="phone" :size="14" />
                            <span>{{ __('casos.contacts_button') }}</span>
                        </a>
                        @endcan
                    </div>
                </div>

                @if($contactos->isNotEmpty())
                    <div style="margin-top:12px;display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:6px;">
                        @foreach($contactos as $c)
                            <div style="border:1px solid var(--border);border-radius:6px;padding:6px 8px;">
                                <div class="flex items-center justify-between" style="gap:6px;">
                                    <span class="text-xs font-medium text-ink">{{ ucfirst($c->tipo) }}</span>
                                    @if($c->es_principal)
                                        <span class="font-semibold text-brand-500" style="font-size:9px;text-transform:uppercase;">{{ __('contactos.badge_principal') }}</span>
                                    @endif
                                </div>
                                <div class="text-sm text-ink-600" style="word-break:break-all;">{{ $c->valor }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-ui.card>

            <x-ui.card :title="__('casos.cases_count', ['count' => $casos->count(), 'entidades' => $rotuloCasos])" style="margin-top:12px;">
                @can('casos.crear', $proyectoActivo->id)
                    <a href="{{ route('proyectos.casos.crear', ['proyecto_id' => $proyectoActivo->id, 'persona' => $persona->public_id]) }}"
                       wire:navigate class="btn btn-primary btn-sm" style="margin-bottom:8px;">
                        <x-ui.icon name="plus" :size="13" />
                        <span>{{ __('casos.new_case', ['entidad' => $rotuloCaso]) }}</span>
                    </a>
                @endcan
                @if($casos->isEmpty())
                    <x-ui.empty-state :title="__('casos.no_open_cases', ['entidades' => $rotuloCasos])" :message="__('casos.no_open_cases_desc', ['entidades' => $rotuloCasos])" />
                @else
                    <div class="flex flex-col" style="gap:6px;margin:-4px -4px 0;">
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
                                    class="flex items-center"
                                    style="text-align:left;padding:10px 8px;border-radius:6px;background:{{ $activo ? 'var(--primary-soft)' : 'transparent' }};border:1px solid {{ $activo ? 'var(--primary-soft-border)' : 'transparent' }};gap:10px;cursor:pointer;width:100%;">
                                <x-ui.badge :tone="$tipoTone">{{ ucfirst(str_replace('_', ' ', $c->tipo_caso)) }}</x-ui.badge>
                                <div class="flex-1 min-w-0">
                                    <div class="font-mono text-base font-semibold text-ink">{{ $c->referencia }}</div>
                                    <div class="text-xs text-ink-500">{{ $c->cartera_nombre }}</div>
                                    @if($c->tipo_caso === 'cobranza')
                                        <div class="flex flex-wrap gap-2 mt-2 text-sm">
                                            <span class="font-mono font-medium">{{ $c->moneda }} {{ $c->saldo_total === null ? '—' : numero_local($c->saldo_total) }}</span>
                                            <span class="text-ink-500">{{ $c->dias_mora === null ? 'Mora sin dato' : numero_local($c->dias_mora, 0).' días de mora' }}</span>
                                        </div>
                                    @endif
                                    <div class="text-xs text-ink-500 mt-1">Asesor: {{ $c->asesor_nombre ?? 'Sin asignar' }}</div>
                                    <div class="text-xs text-ink-500">
                                        {{ $c->estado_caso_nombre }}
                                        @if($c->tiene_compromiso_vigente)
                                            · <span class="font-medium text-success-500">{{ __('casos.active_commitment_label') }}</span>
                                        @endif
                                    </div>
                                    {{-- En qué punto está el caso. La consulta traía las dos
                                         cosas desde siempre y ninguna vista las pintaba: para
                                         saber si a esta persona se la llamó ayer y qué dijo,
                                         había que bajar al historial. --}}
                                    <div class="text-xs text-ink-400">
                                        @if($c->fecha_ultima_gestion)
                                            {{ $c->resultado_ultimo_nombre ?? __('casos.last_outcome_none') }}
                                            · {{ hora_local($c->fecha_ultima_gestion) }}
                                        @else
                                            {{ __('casos.last_outcome_never') }}
                                        @endif
                                    </div>
                                </div>
                                <div class="text-xs text-ink-500 text-right">
                                    {{ __('casos.prio_label', ['value' => $c->prioridad]) }}
                                </div>
                            </button>
                        @endforeach
                    </div>
                @endif
            </x-ui.card>

            @if($casoActivo)
                {{-- Quién responde por esta cuenta. Si no es de nadie y el proyecto
                     lo permite, el asesor la toma antes de empezar a trabajarla:
                     así aparece en su bandeja mientras la gestiona, y no al
                     terminar. --}}
                @if($puedeTomar || $duenioCaso)
                    <div class="flex items-center justify-between gap-2 mt-3">
                        <div class="text-sm text-ink-500">
                            {{ __('casos.assign_owner') }}:
                            <strong class="text-ink">{{ $duenioCaso ?? __('casos.assign_unowned') }}</strong>
                        </div>
                        @if($puedeTomar && ! $duenioCaso)
                            <button type="button" class="btn btn-secondary btn-sm"
                                    wire:click="tomarCuenta({{ (int) $casoActivo->id }})"
                                    wire:loading.attr="disabled" wire:target="tomarCuenta">
                                {{ __('casos.assign_take') }}
                            </button>
                        @endif
                    </div>
                @endif

                @if($mensajeAsignacion !== '')
                    <x-ui.alert tone="success" class="mt-2">{{ $mensajeAsignacion }}</x-ui.alert>
                @endif
                @error('asignacion')
                    <x-ui.alert tone="danger" class="mt-2">{{ $message }}</x-ui.alert>
                @enderror

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
                        <div class="text-right" style="margin-top:8px;">
                            <a href="{{ route('proyectos.casos.editar', ['proyecto_id' => $proyectoActivo->id, 'caso' => $casoActivo->public_id]) }}"
                               wire:navigate class="btn btn-ghost btn-sm">
                                <x-ui.icon name="edit" :size="13" />
                                <span>{{ __('casos.edit_case', ['entidad' => $rotuloCaso]) }}</span>
                            </a>
                        </div>
                    @endcan
                </div>

                {{-- Los datos de la cuenta, en lectura y plegados por grupo.
                     Antes eran 34 inputs sueltos dentro del formulario de
                     gestión —98 en el proyecto grande—, en una rejilla de tres
                     columnas y sin agrupar. Aquí se leen, que es lo que el
                     gestor hace con ellos mientras habla; editarlos es un clic
                     y ocurre en la pantalla que ya existía para eso. --}}
                @if($gruposCamposCaso !== [])
                    <x-ui.card :title="__('casos.case_fields_title', ['entidad' => $rotuloCaso])" style="margin-top:12px;">
                        <div class="flex flex-col" style="gap:2px;">
                            @foreach($gruposCamposCaso as $i => $grupo)
                                <details @if($i === 0) open @endif class="cp-grupo">
                                    <summary class="flex items-center text-xs uppercase-spaced text-ink-600"
                                             style="cursor:pointer;list-style:none;gap:6px;padding:6px 0;">
                                        <x-ui.icon name="chevron-right" :size="12" class="cp-grupo-flecha" />
                                        <span>{{ $grupo['nombre'] }}</span>
                                        <span class="text-ink-500 font-normal">({{ count($grupo['campos']) }})</span>
                                    </summary>
                                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px;padding:4px 0 10px;">
                                        @foreach($grupo['campos'] as $fila)
                                            <x-cp.valor :campo="$fila['campo']" :valor="$fila['valor']" />
                                        @endforeach
                                    </div>
                                </details>
                            @endforeach
                        </div>
                    </x-ui.card>
                @endif

                @if(isset($compromisosResueltos) && $compromisosResueltos->isNotEmpty())
                    <x-ui.card :title="__('casos.resolved_commitments', ['count' => $compromisosResueltos->count()])" style="margin-top:12px;">
                        <ul class="flex flex-col text-sm" style="gap:6px;">
                            @foreach($compromisosResueltos as $c)
                                @php
                                    $estadoTone = match ($c->estado) {
                                        'cumplido' => 'success',
                                        'roto' => 'danger',
                                        'cancelado' => 'neutral',
                                        default => 'neutral',
                                    };
                                @endphp
                                <li class="flex items-center justify-between gap-2" style="padding:6px 8px;border:1px solid var(--border);border-radius:6px;">
                                    <div class="min-w-0">
                                        <div class="flex items-center" style="gap:6px;">
                                            <x-ui.badge :tone="$estadoTone" size="sm">{{ ucfirst($c->estado) }}</x-ui.badge>
                                            <span class="text-xs text-ink-500">
                                                {{ str_replace('_', ' ', $c->tipo_compromiso) }}
                                            </span>
                                        </div>
                                        <div class="text-xs text-ink-500" style="margin-top:2px;">
                                            {{ __('casos.expiry_label', ['date' => fecha_local($c->fecha_vencimiento)]) }}
                                        </div>
                                    </div>
                                    <div class="text-xs text-ink-600 text-right">
                                        @if($c->fecha_resolucion)
                                            {{ __('casos.resolved_label') }}<br>
                                            <span class="font-mono">{{ fecha_local($c->fecha_resolucion) }}</span>
                                        @else
                                            <span class="text-ink-500">{{ __('casos.no_date') }}</span>
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
                        :key="'panel-ent-persona-'.$persona->id.'-caso-'.$casoActivo->id" />
                @endcan
            @endif
        </div>

        {{-- Col centro: formulario nueva gestión. Los campos personalizados del CASO
             se editan en "Editar caso"; aquí los del ámbito gestión van inline en NuevaGestion. --}}
        <div class="vt-col-mid">
            @if($casoActivo && $puedeGestionarCaso)
                <x-ui.card :title="__('casos.register_gestion_title')">
                    <livewire:casos.nueva-gestion
                        :casoId="$casoActivo->id"
                        :personaId="$persona->id"
                        :tipoCaso="$casoActivo->tipo_caso"
                        :key="'nueva-gestion-'.$casoActivo->id" />
                </x-ui.card>
            @elseif($casoActivo)
                <x-ui.card title="Cuenta en consulta">
                    <p class="text-sm text-ink-500">Puedes consultar esta cuenta. Para registrar gestiones debe estar asignada a ti o contar con permiso de colaboración.</p>
                </x-ui.card>
            @else
                <x-ui.card>
                    <x-ui.empty-state :title="__('casos.select_case_title', ['entidad' => $rotuloCaso, 'un' => $rotuloArticulo])" :message="__('casos.select_case_desc', ['entidad' => $rotuloCaso, 'un' => $rotuloArticulo])" />
                </x-ui.card>
            @endif
        </div>

        {{-- Col derecha: historial --}}
        <div class="vt-col-right">
            @if($casoActivo)
                <x-ui.card :title="__('casos.history_title', ['count' => $historial->count()])">
                    <x-slot:actions>
                        <div class="flex items-center gap-1" title="{{ __('casos.history_effective_hint') }}">
                            <button type="button" wire:click="alternarSoloEfectivas"
                                    class="btn btn-sm {{ $soloEfectivas ? 'btn-ghost' : 'btn-secondary' }}"
                                    @disabled(! $soloEfectivas)>{{ __('casos.history_all') }}</button>
                            <button type="button" wire:click="alternarSoloEfectivas"
                                    class="btn btn-sm {{ $soloEfectivas ? 'btn-secondary' : 'btn-ghost' }}"
                                    @disabled($soloEfectivas)>{{ __('casos.history_effective') }}</button>
                        </div>
                    </x-slot:actions>
                    @if($historial->isEmpty())
                        <x-ui.empty-state :title="__('casos.no_gestions')" :message="__('casos.no_gestions_desc')" />
                    @else
                        <x-ui.timeline>
                            @foreach($historial as $g)
                                @php
                                    // El color sale de `resultados.es_contacto_efectivo`, que es
                                    // lo que el proyecto declara de cada resultado. Antes salía de
                                    // comparar el nombre en minúsculas contra una lista en
                                    // español: un proyecto que llame «Contacto con tercero» a un
                                    // resultado efectivo lo pintaba gris, y el catálogo es de cada
                                    // mandante.
                                    $tone = $g->es_contacto_efectivo ? 'success' : 'neutral';
                                @endphp
                                <x-ui.timeline-item
                                    :tone="$tone"
                                    :timestamp="hora_local($g->creada_en)"
                                    :title="($g->resultado_nombre ?? '—') . ' · ' . ($g->tipo_gestion_nombre ?? '—')">
                                    @if($g->notas)
                                        <div style="margin-bottom:4px;">{{ $g->notas }}</div>
                                    @endif
                                    <div class="text-xs text-ink-500">
                                        {{ $g->canal_nombre ?? '—' }}
                                        · {{ $g->usuario_nombre ?? '—' }}
                                        @if($g->duracion_segundos)
                                            · {{ (int) floor($g->duracion_segundos / 60) }}m {{ $g->duracion_segundos % 60 }}s
                                        @endif
                                    </div>
                                    @if($g->motivo_no_contacto_nombre || $g->causa_nombre)
                                        <div class="flex flex-wrap gap-1" style="margin-top:4px;">
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
                                        <dl class="text-xs" style="margin-top:6px;padding-top:6px;border-top:1px dashed var(--border);display:grid;grid-template-columns:auto 1fr;gap:2px 8px;">
                                            @foreach($valoresCamposGestion[$g->id] as $cp)
                                                <dt class="text-ink-500">{{ $cp['etiqueta'] }}</dt>
                                                <dd class="text-ink" style="margin:0;">{{ $cp['valor'] }}</dd>
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
                    <x-ui.empty-state :title="__('casos.no_active_case')" :message="__('casos.no_active_case_desc', ['entidad' => $rotuloCaso, 'un' => $rotuloArticulo])" />
                </x-ui.card>
            @endif
        </div>
    </div>
</div>
