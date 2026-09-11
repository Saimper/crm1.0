@php
    $nombreCompromiso = fn (string $tipo): string => match ($tipo) {
        'promesa_pago'      => __('casos.commitment_promise'),
        'cierre_venta'      => __('casos.commitment_close'),
        'resolucion_ticket' => __('casos.commitment_resolution'),
        default             => __('casos.commitment_service'),
    };
@endphp

<div class="vt-grid">

    {{-- ================= IZQUIERDA: identidad y cuentas ================= --}}
    <aside class="vt-col-left workspace-identity">

        <section class="workspace-person">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <div class="workspace-document">
                        {{ $persona->tipo_identificacion_codigo ?? 'ID' }}
                        · <span class="font-mono normal-case tracking-normal">{{ $persona->identificacion }}</span>
                    </div>
                    <h2 class="workspace-person-name">
                        {{ $nombrePersona !== '' && $nombrePersona === mb_strtoupper($nombrePersona) ? mb_convert_case($nombrePersona, MB_CASE_TITLE, 'UTF-8') : ($nombrePersona ?: '—') }}
                    </h2>
                    <div class="sr-only">
                        {{ $persona->tipo_persona === 'juridica' ? __('casos.person_juridica') : __('casos.person_fisica') }}
                        @if($persona->tipo_persona === 'fisica' && $persona->fecha_nacimiento)
                            · {{ __('casos.born_abbrev') }} <span class="font-mono">{{ fecha_local($persona->fecha_nacimiento) }}</span>
                        @endif
                    </div>
                </div>
                <div class="flex gap-1 shrink-0">
                    @can('personas.editar', $proyectoActivo->id)
                        <a href="{{ route('proyectos.personas.editar', ['proyecto_id' => $proyectoActivo->id, 'persona' => $persona->public_id]) }}"
                           wire:navigate class="btn btn-ghost btn-icon" title="{{ __('casos.edit_person') }}">
                            <x-ui.icon name="edit" :size="14" />
                        </a>
                    @endcan
                    @can('contactos.ver', $proyectoActivo->id)
                        <a href="{{ route('proyectos.personas.contactos', ['proyecto_id' => $proyectoActivo->id, 'persona' => $persona->public_id]) }}"
                           wire:navigate class="btn btn-ghost btn-icon" title="{{ __('casos.contacts_button') }}">
                            <x-ui.icon name="phone" :size="14" />
                        </a>
                    @endcan
                </div>
            </div>

            @if($contactos->isNotEmpty())
                <div class="workspace-contacts">
                    @foreach($contactos as $c)
                        @php
                            $esTelefono = in_array((string) $c->tipo, ['telefono', 'movil', 'celular', 'whatsapp'], true);
                            $icono = match ((string) $c->tipo) {
                                'correo', 'email' => 'mail',
                                'direccion' => 'map-pin',
                                'whatsapp'  => 'message-square',
                                default     => $esTelefono ? 'phone' : 'user',
                            };
                        @endphp
                        <div class="vt-contacto" wire:key="contacto-{{ $c->id }}">
                            <span class="vt-contacto-icono"><x-ui.icon :name="$icono" :size="13" /></span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5 text-xs text-ink-500">
                                    {{ ucfirst((string) $c->tipo) }}
                                    @if($c->es_principal)
                                        <span class="sr-only">{{ __('contactos.badge_principal') }}</span>
                                    @endif
                                </div>
                                <div class="workspace-contact-value" title="{{ $c->valor }}">{{ $c->valor }}</div>
                            </div>
                            @if($casoActivo && $puedeGestionarCaso)
                                <button type="button"
                                        class="btn btn-sm h-6 px-2 text-xs border-brand-100 text-brand-700 hover:bg-brand-50"
                                        x-on:click="$dispatch('usar-contacto', { contactoId: {{ (int) $c->id }} })">{{ __('casos.use_contact') }}</button>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        @if($casoActivo)
            {{-- ---- Resumen ---- --}}
            <div id="panel-resumen" aria-label="Resumen" class="workspace-header-summary">
                @if($compromisoActivo)
                    <section>
                        <h3 class="text-base font-semibold text-ink mb-2.5">{{ __('casos.pending_commitment') }}</h3>
                        <div class="border border-success-200 bg-success-50 rounded-lg px-3.5 py-3 flex items-center justify-between gap-3 flex-wrap">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-semibold text-ink">{{ $nombreCompromiso($compromisoActivo->tipo_compromiso) }}</span>

                                    @if($compromisoActivo->monto !== null)
                                        <span class="font-mono font-semibold text-ink">{{ $compromisoActivo->moneda }} {{ numero_local($compromisoActivo->monto) }}</span>
                                    @elseif($compromisoActivo->detalle)
                                        <span class="text-ink">{{ $compromisoActivo->detalle }}</span>
                                    @endif
                                </div>
                                <div class="text-sm text-ink-600 mt-0.5">
                                    {{ __('casos.expires_on', ['date' => fecha_local($compromisoActivo->fecha_vencimiento)]) }}
                                    @if($compromisoActivo->tipo_pago_nombre) · {{ $compromisoActivo->tipo_pago_nombre }} @endif

                                </div>
                            </div>
                            @if($puedeGestionarCaso)
                                <div class="flex gap-1.5 items-center flex-wrap">
                                    @can('compromisos.crear', $proyectoActivo->id)
                                        <a href="{{ route('proyectos.compromisos.editar', ['proyecto_id' => $proyectoActivo->id, 'compromiso' => $compromisoActivo->public_id]) }}"
                                           wire:navigate class="btn btn-secondary btn-sm">{{ __('common.edit') }}</a>
                                    @endcan
                                    @include('casos::partials.resolver-compromiso', ['compromiso' => $compromisoActivo, 'clave' => 'resumen'])
                                </div>
                            @endif
                        </div>
                    </section>
                @endif

                <section aria-label="Resumen de la cuenta">
                    @if($casoActivo->tipo_caso === 'cobranza')
                        <dl class="workspace-balances">
                            <div><dt>Saldo pendiente</dt><dd>{{ $casoActivo->moneda }} {{ $casoActivo->saldo_total === null ? '—' : numero_local($casoActivo->saldo_total) }}</dd></div>
                            <div><dt>Mora</dt><dd class="workspace-balance-secondary">{{ $casoActivo->dias_mora === null ? '—' : numero_local($casoActivo->dias_mora, 0).' días' }}</dd></div>
                            <div><dt>Cuota mensual</dt><dd class="workspace-balance-secondary">{{ $casoCobranza?->cuota_mensual === null ? '—' : $casoActivo->moneda.' '.numero_local($casoCobranza->cuota_mensual) }}</dd></div>
                        </dl>
                    @else
                        <dl class="vt-dl text-sm">
                            @if($casoActivo->tipo_caso === 'ticket_cx')
                                @include('cx::partials.resumen-caso', ['ticket' => $casoTicketCx])
                            @elseif($casoActivo->tipo_caso === 'lead_venta')
                                @include('venta::partials.resumen-caso', ['lead' => $casoLeadVenta])
                            @else
                                @include('servicio::partials.resumen-caso', ['servicio' => $casoServicio])
                            @endif
                        </dl>
                    @endif
                </section>

            </div>
        @endif
        </section>

        <section class="workspace-accounts">
            <div class="flex items-center justify-between mb-2.5">
                <h3 class="text-base font-semibold text-ink">
                    {{ ucfirst($rotuloCasos) }} <span class="text-ink-400 font-medium">({{ $casos->count() }})</span>
                </h3>
                @can('casos.crear', $proyectoActivo->id)
                    <a href="{{ route('proyectos.casos.crear', ['proyecto_id' => $proyectoActivo->id, 'persona' => $persona->public_id]) }}"
                       wire:navigate class="btn-link -mr-2">+ {{ __('casos.new_case', ['entidad' => $rotuloCaso]) }}</a>
                @endcan
            </div>

            @if($casos->isEmpty())
                <x-ui.empty-state :title="__('casos.no_open_cases', ['entidades' => $rotuloCasos])" :message="__('casos.no_open_cases_desc', ['entidades' => $rotuloCasos])" />
            @else
                <div class="workspace-account-options">
                    @foreach($casos as $c)
                        @php $activo = $casoActivo && $c->public_id === $casoActivo->public_id; @endphp
                        <button type="button" wire:click="seleccionarCaso('{{ $c->public_id }}')" wire:key="cuenta-{{ $c->id }}"
                                class="vt-cuenta {{ $activo ? 'active' : '' }}" @if($activo) aria-current="true" @endif>
                            <div class="flex justify-between items-center gap-2">
                                <span class="font-mono text-base font-semibold text-ink truncate">{{ $c->referencia }}</span>
                                <span class="workspace-account-state">{{ $c->estado_caso_nombre }}</span>
                            </div>
                            <div class="text-xs text-ink-500 mt-px truncate">{{ $c->cartera_nombre }}</div>
                            @if($c->tiene_compromiso_vigente)
                                <span class="text-xs text-success-600">{{ __('casos.active_commitment_label') }}</span>
                            @endif
                        </button>
                    @endforeach
                </div>
            @endif

            @if($casoActivo)
                {{-- Quién responde por esta cuenta. Si no es de nadie y el proyecto
                     lo permite, el asesor la toma antes de empezar a trabajarla. --}}
                <div class="mt-3 pt-2.5 border-t border-ink-100 flex justify-between items-center gap-2 text-sm text-ink-500">
                    <span class="min-w-0 truncate">
                        <span class="sr-only">{{ __('casos.assign_owner') }}</span>
                        <strong class="text-ink font-medium">{{ $duenioCaso ?? __('casos.assign_unowned') }}</strong>
                    </span>
                    <span class="flex items-center gap-2 shrink-0">
                        @if($puedeTomar && ! $duenioCaso)
                            <button type="button" class="btn btn-secondary btn-sm h-[26px]"
                                    wire:click="tomarCuenta({{ (int) $casoActivo->id }})"
                                    wire:loading.attr="disabled" wire:target="tomarCuenta"
                                    title="{{ __('casos.assign_take_title') }}">{{ __('casos.assign_take') }}</button>
                        @endif
                        <span class="text-ink-400">{{ __('casos.priority_label', ['value' => $casoActivo->prioridad]) }}</span>
                    </span>
                </div>
                @if($mensajeAsignacion !== '')
                    <x-ui.alert tone="success" class="mt-2">{{ $mensajeAsignacion }}</x-ui.alert>
                @endif
                @error('asignacion')
                    <x-ui.alert tone="danger" class="mt-2">{{ $message }}</x-ui.alert>
                @enderror
            @endif
        @if($casoActivo)
            {{-- ---- Cuenta: la ficha completa, en lectura ---- --}}
            <details id="panel-cuenta" class="workspace-header-account" wire:key="account-details-{{ $casoActivo->id }}">
                <summary>Detalles de {{ $rotuloCaso }}</summary>
                <section>
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
                        <div class="flex justify-end mt-3">
                            <a href="{{ route('proyectos.casos.editar', ['proyecto_id' => $proyectoActivo->id, 'caso' => $casoActivo->public_id]) }}"
                               wire:navigate class="btn btn-secondary btn-sm">
                                <x-ui.icon name="edit" :size="13" />
                                <span>{{ __('casos.edit_case', ['entidad' => $rotuloCaso]) }}</span>
                            </a>
                        </div>
                    @endcan
                </section>

            </details>
        @endif
        </section>
    </aside>

    {{-- ================= CENTRO: pestañas de la cuenta ================= --}}
    <section aria-label="Información de la cuenta" class="vt-col-mid card overflow-hidden" x-data="{ tab: 'campos' }">
        @if($casoActivo)
            <div class="flex items-end justify-between gap-3 px-5 border-b border-ink-200 flex-wrap">
                <div class="vt-tabs" role="tablist" aria-label="Información del cliente" x-on:keydown.arrow-right.prevent="$event.target.nextElementSibling?.focus()" x-on:keydown.arrow-left.prevent="$event.target.previousElementSibling?.focus()">
                    <button type="button" role="tab" id="tab-campos" aria-controls="panel-campos" :aria-selected="tab === 'campos'" class="vt-tab" :class="{ active: tab === 'campos' }" x-on:click="tab = 'campos'">{{ __('casos.tab_fields') }}</button>
                    <button type="button" role="tab" id="tab-historial" aria-controls="panel-historial" :aria-selected="tab === 'historial'" class="vt-tab" :class="{ active: tab === 'historial' }" x-on:click="tab = 'historial'">
                        {{ __('casos.tab_history') }} <span class="count">{{ $historial->count() }}</span>
                    </button>
                    <button type="button" role="tab" id="tab-compromisos" aria-controls="panel-compromisos" :aria-selected="tab === 'compromisos'" class="vt-tab" :class="{ active: tab === 'compromisos' }" x-on:click="tab = 'compromisos'">
                        {{ __('casos.tab_commitments') }}
                        @if($compromisosPendientes->isNotEmpty())<span class="count">{{ $compromisosPendientes->count() }}</span>@endif
                    </button>
                </div>
            </div>

            {{-- ---- Historial ---- --}}
            <div id="panel-historial" role="tabpanel" aria-labelledby="tab-historial" x-show="tab === 'historial'" x-cloak class="p-5">
                <div class="flex items-center justify-between mb-3 gap-3">
                    <h3 class="text-base font-semibold text-ink">{{ __('casos.tab_history') }} <span class="text-ink-400 font-medium">({{ $historial->count() }})</span></h3>
                    {{-- Enseñar sólo lo que llegó a ser una conversación. --}}
                    <div class="seg" title="{{ __('casos.history_effective_hint') }}">
                        <button type="button" wire:click="alternarSoloEfectivas" class="seg-btn {{ $soloEfectivas ? '' : 'active' }}" @disabled(! $soloEfectivas)>{{ __('casos.history_all') }}</button>
                        <button type="button" wire:click="alternarSoloEfectivas" class="seg-btn {{ $soloEfectivas ? 'active' : '' }}" @disabled($soloEfectivas)>{{ __('casos.history_effective') }}</button>
                    </div>
                </div>
                @if($historial->isEmpty())
                    <x-ui.empty-state :title="__('casos.no_gestions')" :message="__('casos.no_gestions_desc')" />
                @else
                    <x-ui.timeline>
                        @foreach($historial as $g)
                            @include('casos::partials.gestion-historial', ['g' => $g, 'campos' => $valoresCamposGestion[$g->id] ?? [], 'clave' => 'hist'])
                        @endforeach
                    </x-ui.timeline>
                @endif
            </div>

            {{-- ---- Campos: los datos de la cuenta, en lectura y por grupo.
                 Se editan en «Editar cuenta», que es la pantalla dueña del dato. ---- --}}
            <div id="panel-campos" role="tabpanel" aria-labelledby="tab-campos" x-show="tab === 'campos'" class="px-5 pt-2 pb-5">
                @include('casos::partials.campos-lectura')
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
            </div>

            {{-- ---- Compromisos ---- --}}
            <div id="panel-compromisos" role="tabpanel" aria-labelledby="tab-compromisos" x-show="tab === 'compromisos'" x-cloak class="p-5 flex flex-col gap-6">
                <section>
                    <h3 class="text-base font-semibold text-ink mb-2">{{ __('casos.pending_of_person') }} <span class="text-ink-400 font-medium">({{ $compromisosPendientes->count() }})</span></h3>
                    @if($compromisosPendientes->isEmpty())
                        <div class="border border-dashed border-ink-200 rounded-lg p-3.5 text-center text-sm text-ink-500">{{ __('casos.no_pending_commitments') }}</div>
                    @else
                        <div class="flex flex-col text-sm">
                            @foreach($compromisosPendientes as $pendiente)
                                @php
                                    $cuenta = $casos->firstWhere('id', $pendiente->caso_id);
                                    $esActiva = (int) $casoActivo->id === (int) $pendiente->caso_id;
                                @endphp
                                <div wire:key="pendiente-{{ $pendiente->id }}"
                                     @class(['flex items-center justify-between gap-3 flex-wrap py-2.5 border-b border-ink-100', 'bg-surface-50 -mx-2 px-2 rounded-md' => $esActiva])>
                                    <div class="min-w-0 flex items-center gap-x-4 gap-y-1 flex-wrap">
                                        <div class="min-w-[150px]">
                                            @if($esActiva)
                                                <div class="font-mono font-semibold text-brand-700">{{ $cuenta->referencia }}</div>
                                            @else
                                                <button type="button" wire:click="seleccionarCaso('{{ $cuenta->public_id }}')" class="font-mono font-semibold text-brand-500 hover:underline">{{ $cuenta->referencia }}</button>
                                            @endif
                                            <div class="text-xs text-ink-500">{{ $cuenta->cartera_nombre }}</div>
                                        </div>
                                        <span class="text-ink">{{ $nombreCompromiso($pendiente->tipo_compromiso) }}</span>
                                        <span class="text-ink-500">{{ __('cobranza.vencimiento') }} <span class="font-mono text-ink">{{ fecha_local($pendiente->fecha_vencimiento) }}</span></span>
                                        <span class="font-mono text-ink">@if($pendiente->monto !== null){{ $pendiente->moneda }} {{ numero_local($pendiente->monto) }}@else{{ $pendiente->detalle ?? '—' }}@endif</span>
                                    </div>
                                    <div class="flex items-center gap-1.5 shrink-0">
                                        @if($esActiva && $puedeGestionarCaso)
                                            @can('compromisos.crear', $proyectoActivo->id)
                                                <a href="{{ route('proyectos.compromisos.editar', ['proyecto_id' => $proyectoActivo->id, 'compromiso' => $pendiente->public_id]) }}"
                                                   wire:navigate class="btn btn-secondary btn-sm">{{ __('common.edit') }}</a>
                                            @endcan
                                            @include('casos::partials.resolver-compromiso', ['compromiso' => $pendiente, 'clave' => 'tabla'])
                                        @elseif(! $esActiva)
                                            <span class="text-xs text-ink-400">{{ __('casos.select_account_to_resolve', ['un' => $rotuloArticulo, 'entidad' => $rotuloCaso]) }}</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>

                <section>
                    <h3 class="text-base font-semibold text-ink mb-2">{{ __('casos.resolved_title') }} <span class="text-ink-400 font-medium">({{ $compromisosResueltos->count() }})</span></h3>
                    @if($compromisosResueltos->isEmpty())
                        <div class="border border-dashed border-ink-200 rounded-lg p-3.5 text-center text-sm text-ink-500">{{ __('casos.no_resolved_commitments') }}</div>
                    @else
                        <div class="flex flex-col text-sm">
                            @foreach($compromisosResueltos as $c)
                                @php
                                    $estadoTone = match ($c->estado) {
                                        'cumplido' => 'success',
                                        'roto'     => 'danger',
                                        default    => 'neutral',
                                    };
                                @endphp
                                <div class="flex items-center gap-2.5 py-[9px] border-b border-ink-100 flex-wrap" wire:key="resuelto-{{ $c->id }}">
                                    <x-ui.badge :tone="$estadoTone" size="sm" class="min-w-[74px] justify-center">{{ ucfirst($c->estado) }}</x-ui.badge>
                                    <span class="text-ink-500">{{ $nombreCompromiso($c->tipo_compromiso) }}</span>
                                    <span class="text-ink-500">{{ __('casos.expiry_label', ['date' => fecha_local($c->fecha_vencimiento)]) }}</span>
                                    <span class="ml-auto text-ink-500">
                                        {{ __('casos.resolved_label') }}
                                        <span class="font-mono text-ink">{{ $c->fecha_resolucion ? fecha_local($c->fecha_resolucion) : __('casos.no_date') }}</span>
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>
            </div>
        @else
            <div class="p-5">
                <x-ui.empty-state :title="__('casos.select_case_title', ['entidad' => $rotuloCaso, 'un' => $rotuloArticulo])" :message="__('casos.select_case_desc', ['entidad' => $rotuloCaso, 'un' => $rotuloArticulo])" />
            </div>
        @endif
    </section>

    {{-- ================= DERECHA: registrar gestión ================= --}}
    <aside id="gestion-panel" class="vt-col-right card flex flex-col min-h-0" tabindex="-1">
        @if($casoActivo && $puedeGestionarCaso)
            <div class="px-[18px] pt-3.5 pb-3 border-b border-ink-200 flex items-center justify-between gap-2 shrink-0">
                <h3 class="text-md font-semibold text-ink">{{ __('casos.register_gestion_title') }}</h3>

            </div>
            <livewire:casos.nueva-gestion
                :casoId="$casoActivo->id"
                :personaId="$persona->id"
                :tipoCaso="$casoActivo->tipo_caso"
                :key="'nueva-gestion-'.$casoActivo->id" />
        @elseif($casoActivo)
            <div class="px-[18px] pt-3.5 pb-3 border-b border-ink-200">
                <h3 class="text-md font-semibold text-ink">{{ __('casos.account_readonly_title') }}</h3>
            </div>
            <p class="p-[18px] text-sm text-ink-500">{{ __('casos.account_readonly_desc') }}</p>
        @else
            <x-ui.empty-state :title="__('casos.no_active_case')" :message="__('casos.no_active_case_desc', ['entidad' => $rotuloCaso, 'un' => $rotuloArticulo])" />
        @endif
    </aside>
</div>
