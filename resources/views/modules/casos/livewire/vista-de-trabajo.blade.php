@php
    $rotulo = ucfirst($rotuloCaso);
    $totalCampos = array_sum(array_map(fn (array $g): int => count($g['campos']), $gruposCamposCaso));

    $tonoTipo = fn (string $tipo): string => match ($tipo) {
        'cobranza'   => 'warning',
        'ticket_cx'  => 'info',
        'lead_venta' => 'success',
        'servicio'   => 'primary',
        default      => 'neutral',
    };
    $nombreTipo = fn (string $tipo): string => match ($tipo) {
        'cobranza'   => 'Cobranza',
        'ticket_cx'  => 'Ticket CX',
        'lead_venta' => 'Lead venta',
        'servicio'   => 'Servicio',
        default      => ucfirst(str_replace('_', ' ', $tipo)),
    };
    $nombreCompromiso = fn (string $tipo): string => match ($tipo) {
        'promesa_pago'      => __('casos.commitment_promise'),
        'cierre_venta'      => __('casos.commitment_close'),
        'resolucion_ticket' => __('casos.commitment_resolution'),
        default             => __('casos.commitment_service'),
    };
    // La mora se colorea por gravedad: sin dato en gris, al día en verde,
    // hasta 60 días en ámbar y a partir de ahí en rojo.
    $tonoMora = fn ($dias): string => $dias === null
        ? 'text-ink-500'
        : ((int) $dias >= 60 ? 'text-danger-500 font-semibold' : ((int) $dias > 0 ? 'text-warning-600 font-semibold' : 'text-success-600'));
    $textoMora = fn ($dias): string => $dias === null
        ? __('casos.no_overdue_data')
        : __('casos.days_overdue', ['days' => numero_local($dias, 0)]);
    $iniciales = fn (?string $nombre): string => collect(preg_split('/\s+/', trim((string) $nombre)) ?: [])
        ->filter()->take(2)->map(fn (string $p): string => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
@endphp

<div class="vt-grid">

    {{-- ================= IZQUIERDA: identidad y cuentas ================= --}}
    <aside class="vt-col-left flex flex-col gap-4">

        <section class="card p-[18px]">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <div class="label-xs">
                        {{ $persona->tipo_identificacion_codigo ?? 'ID' }}
                        · <span class="font-mono normal-case tracking-normal">{{ $persona->identificacion }}</span>
                    </div>
                    <h2 class="mt-1 text-3xl font-semibold leading-tight tracking-tight text-ink">
                        {{ $nombrePersona !== '' ? $nombrePersona : '—' }}
                    </h2>
                    <div class="mt-1 text-xs text-ink-500">
                        {{ $persona->tipo_persona === 'juridica' ? __('casos.person_juridica') : __('casos.person_fisica') }}
                        @if($persona->tipo_persona === 'fisica' && $persona->fecha_nacimiento)
                            · {{ __('casos.born_abbrev') }} <span class="font-mono">{{ fecha_local($persona->fecha_nacimiento) }}</span>
                        @endif
                    </div>
                </div>
                <div class="flex gap-1 shrink-0">
                    @can('personas.editar', $proyectoActivo->id)
                        <a href="{{ route('proyectos.personas.editar', ['proyecto_id' => $proyectoActivo->id, 'persona' => $persona->public_id]) }}"
                           wire:navigate class="btn btn-secondary btn-sm btn-icon w-[30px] h-[30px]" title="{{ __('casos.edit_person') }}">
                            <x-ui.icon name="edit" :size="14" />
                        </a>
                    @endcan
                    @can('contactos.ver', $proyectoActivo->id)
                        <a href="{{ route('proyectos.personas.contactos', ['proyecto_id' => $proyectoActivo->id, 'persona' => $persona->public_id]) }}"
                           wire:navigate class="btn btn-secondary btn-sm btn-icon w-[30px] h-[30px]" title="{{ __('casos.contacts_button') }}">
                            <x-ui.icon name="phone" :size="14" />
                        </a>
                    @endcan
                </div>
            </div>

            @if($contactos->isNotEmpty())
                <div class="mt-4 flex flex-col gap-0.5">
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
                                        <span class="text-[9px] font-semibold tracking-[0.06em] uppercase text-brand-700 bg-brand-50 rounded-xs px-[5px] py-px">{{ __('contactos.badge_principal') }}</span>
                                    @endif
                                </div>
                                <div class="text-base text-ink truncate {{ $esTelefono ? 'font-mono' : '' }}" title="{{ $c->valor }}">{{ $c->valor }}</div>
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
        </section>

        <section class="card px-[18px] pt-3.5 pb-3">
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
                <div class="flex flex-col gap-1.5">
                    @foreach($casos as $c)
                        @php $activo = $casoActivo && $c->public_id === $casoActivo->public_id; @endphp
                        <button type="button" wire:click="seleccionarCaso('{{ $c->public_id }}')" wire:key="cuenta-{{ $c->id }}"
                                class="vt-cuenta {{ $activo ? 'active' : '' }}" @if($activo) aria-current="true" @endif>
                            <div class="flex justify-between items-center gap-2">
                                <span class="font-mono text-base font-semibold text-ink truncate">{{ $c->referencia }}</span>
                                <x-ui.badge :tone="$tonoTipo($c->tipo_caso)" size="sm">{{ $nombreTipo($c->tipo_caso) }}</x-ui.badge>
                            </div>
                            <div class="text-xs text-ink-500 mt-px truncate">{{ $c->cartera_nombre }}</div>
                            @if($c->tipo_caso === 'cobranza')
                                <div class="flex justify-between items-baseline mt-1.5 gap-2">
                                    <span class="font-mono text-base text-ink">{{ $c->moneda }} {{ numero_local($c->saldo_total) }}</span>
                                    <span class="text-xs {{ $tonoMora($c->dias_mora) }}">{{ $textoMora($c->dias_mora) }}</span>
                                </div>
                            @endif
                            <div class="text-xs text-ink-500 mt-1">
                                {{ $c->estado_caso_nombre }}
                                @if($c->tiene_compromiso_vigente)
                                    <span class="text-success-600">· {{ __('casos.active_commitment_label') }}</span>
                                @endif
                            </div>
                            {{-- En qué punto está el caso: último resultado y cuándo. --}}
                            <div class="text-xs text-ink-400 truncate"
                                 @if($c->fecha_ultima_gestion) title="{{ $c->resultado_ultimo_nombre ?? __('casos.last_outcome_none') }} · {{ hora_local($c->fecha_ultima_gestion) }}" @endif>
                                @if($c->fecha_ultima_gestion)
                                    {{ $c->resultado_ultimo_nombre ?? __('casos.last_outcome_none') }} · {{ hora_local($c->fecha_ultima_gestion) }}
                                @else
                                    {{ __('casos.last_outcome_never') }}
                                @endif
                            </div>
                        </button>
                    @endforeach
                </div>
            @endif

            @if($casoActivo)
                {{-- Quién responde por esta cuenta. Si no es de nadie y el proyecto
                     lo permite, el asesor la toma antes de empezar a trabajarla. --}}
                <div class="mt-3 pt-2.5 border-t border-ink-100 flex justify-between items-center gap-2 text-sm text-ink-500">
                    <span class="min-w-0 truncate">
                        {{ __('casos.assign_owner') }}
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
        </section>
    </aside>

    {{-- ================= CENTRO: pestañas de la cuenta ================= --}}
    <main class="vt-col-mid card overflow-hidden" x-data="{ tab: 'resumen' }">
        @if($casoActivo)
            <div class="flex items-end justify-between gap-3 px-5 border-b border-ink-200 flex-wrap">
                <div class="vt-tabs" role="tablist">
                    <button type="button" role="tab" class="vt-tab" :class="{ active: tab === 'resumen' }" x-on:click="tab = 'resumen'">{{ __('casos.tab_summary') }}</button>
                    <button type="button" role="tab" class="vt-tab" :class="{ active: tab === 'historial' }" x-on:click="tab = 'historial'">
                        {{ __('casos.tab_history') }} <span class="count">{{ $historial->count() }}</span>
                    </button>
                    <button type="button" role="tab" class="vt-tab" :class="{ active: tab === 'cuenta' }" x-on:click="tab = 'cuenta'">{{ $rotulo }}</button>
                    @if($gruposCamposCaso !== [])
                        <button type="button" role="tab" class="vt-tab" :class="{ active: tab === 'campos' }" x-on:click="tab = 'campos'">
                            {{ __('casos.tab_fields') }} <span class="count">{{ $totalCampos }}</span>
                        </button>
                    @endif
                    <button type="button" role="tab" class="vt-tab" :class="{ active: tab === 'compromisos' }" x-on:click="tab = 'compromisos'">
                        {{ __('casos.tab_commitments') }}
                        @if($compromisosPendientes->isNotEmpty())<span class="count">{{ $compromisosPendientes->count() }}</span>@endif
                    </button>
                </div>
                <div class="flex items-center gap-2.5 py-2 text-sm text-ink-500 min-w-0">
                    <span class="font-mono text-ink font-semibold truncate">{{ $casoActivo->referencia }}</span>
                    <span class="w-px h-3.5 bg-ink-200 shrink-0"></span>
                    @if($casoActivo->tipo_caso === 'cobranza')
                        <span class="whitespace-nowrap">{{ __('casos.balance_label') }} <strong class="font-mono text-ink font-semibold">{{ $casoActivo->moneda }} {{ numero_local($casoActivo->saldo_total) }}</strong></span>
                        <span class="w-px h-3.5 bg-ink-200 shrink-0"></span>
                        <span class="whitespace-nowrap {{ $tonoMora($casoActivo->dias_mora) }}">{{ $textoMora($casoActivo->dias_mora) }}</span>
                    @else
                        <span class="truncate">{{ $casoActivo->estado_caso_nombre }}</span>
                    @endif
                </div>
            </div>

            {{-- ---- Resumen ---- --}}
            <div x-show="tab === 'resumen'" class="p-5 flex flex-col gap-6">
                @if($compromisoActivo)
                    <section>
                        <h3 class="text-base font-semibold text-ink mb-2.5">{{ __('casos.pending_commitment') }}</h3>
                        <div class="border border-success-200 bg-success-50 rounded-lg px-3.5 py-3 flex items-center justify-between gap-3 flex-wrap">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-semibold text-ink">{{ $nombreCompromiso($compromisoActivo->tipo_compromiso) }}</span>
                                    <x-ui.badge tone="success" size="sm">{{ __('casos.commitment_active') }}</x-ui.badge>
                                    @if($compromisoActivo->monto !== null)
                                        <span class="font-mono font-semibold text-ink">{{ $compromisoActivo->moneda }} {{ numero_local($compromisoActivo->monto) }}</span>
                                    @elseif($compromisoActivo->detalle)
                                        <span class="text-ink">{{ $compromisoActivo->detalle }}</span>
                                    @endif
                                </div>
                                <div class="text-sm text-ink-600 mt-0.5">
                                    {{ __('casos.expires_on', ['date' => fecha_local($compromisoActivo->fecha_vencimiento)]) }}
                                    @if($compromisoActivo->tipo_pago_nombre) · {{ $compromisoActivo->tipo_pago_nombre }} @endif
                                    · {{ $casoActivo->cartera_nombre }}
                                    @if($compromisoActivo->usuario_nombre) · {{ __('casos.registered_by', ['name' => $compromisoActivo->usuario_nombre]) }} @endif
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

                <section>
                    <h3 class="text-base font-semibold text-ink mb-1.5">
                        @if($casoActivo->tipo_caso === 'cobranza') {{ __('cobranza.panel_label') }}
                        @elseif($casoActivo->tipo_caso === 'ticket_cx') {{ __('cx.panel_label') }}
                        @elseif($casoActivo->tipo_caso === 'lead_venta') {{ __('venta.panel_label') }}
                        @else {{ __('servicio.panel_label') }}
                        @endif
                    </h3>
                    <dl class="vt-dl text-base">
                        @if($casoActivo->tipo_caso === 'cobranza')
                            @include('cobranza::partials.resumen-caso', ['cobranza' => $casoCobranza])
                        @elseif($casoActivo->tipo_caso === 'ticket_cx')
                            @include('cx::partials.resumen-caso', ['ticket' => $casoTicketCx])
                        @elseif($casoActivo->tipo_caso === 'lead_venta')
                            @include('venta::partials.resumen-caso', ['lead' => $casoLeadVenta])
                        @elseif($casoActivo->tipo_caso === 'servicio')
                            @include('servicio::partials.resumen-caso', ['servicio' => $casoServicio])
                        @endif
                        <dt>{{ __('casos.col_state') }}</dt>
                        <dd>
                            {{ $casoActivo->estado_caso_nombre }}
                            @if($casoActivo->tiene_compromiso_vigente)<span class="text-success-600">· {{ __('casos.active_commitment_label') }}</span>@endif
                        </dd>
                        <dt>{{ __('casos.advisor_label') }}</dt>
                        <dd class="flex items-center gap-2">
                            @if($duenioCaso)
                                <span class="avatar w-5 h-5 text-[10px]">{{ $iniciales($duenioCaso) }}</span>{{ $duenioCaso }}
                            @else
                                <span class="text-ink-500">{{ __('casos.assign_unowned') }}</span>
                            @endif
                        </dd>
                        <dt>{{ __('casos.col_wallet') }}</dt>
                        <dd>{{ $casoActivo->cartera_nombre }}</dd>
                    </dl>
                    <button type="button" x-on:click="tab = 'cuenta'" class="btn-link mt-2.5 -ml-2">{{ __('casos.see_full_record') }} ›</button>
                </section>

                <section>
                    <div class="flex items-center justify-between mb-1.5">
                        <h3 class="text-base font-semibold text-ink">{{ __('casos.recent_activity') }}</h3>
                        @if($historial->count() > 3)
                            <button type="button" x-on:click="tab = 'historial'" class="btn-link -mr-2">{{ __('casos.see_full_history') }} ›</button>
                        @endif
                    </div>
                    @if($historial->isEmpty())
                        <p class="text-sm text-ink-500">{{ __('casos.no_gestions_desc') }}</p>
                    @else
                        <x-ui.timeline>
                            @foreach($historial->take(3) as $g)
                                @include('casos::partials.gestion-historial', ['g' => $g, 'campos' => [], 'clave' => 'reciente'])
                            @endforeach
                        </x-ui.timeline>
                    @endif
                </section>
            </div>

            {{-- ---- Historial ---- --}}
            <div x-show="tab === 'historial'" x-cloak class="p-5">
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

            {{-- ---- Cuenta: la ficha completa, en lectura ---- --}}
            <div x-show="tab === 'cuenta'" x-cloak class="p-5 flex flex-col gap-6">
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

            {{-- ---- Campos: los datos de la cuenta, en lectura y por grupo.
                 Se editan en «Editar cuenta», que es la pantalla dueña del dato. ---- --}}
            @if($gruposCamposCaso !== [])
                <div x-show="tab === 'campos'" x-cloak class="px-5 pt-2 pb-5">
                    @foreach($gruposCamposCaso as $i => $grupo)
                        <details @if($i === 0) open @endif class="cp-grupo">
                            <summary class="flex items-center gap-2 h-11 cursor-pointer text-base font-semibold text-ink select-none">
                                <span class="cp-grupo-flecha inline-flex text-ink-400"><x-ui.icon name="chevron-right" :size="14" /></span>
                                <span>{{ $grupo['nombre'] }}</span>
                                <span class="text-ink-400 font-medium">({{ count($grupo['campos']) }})</span>
                            </summary>
                            <div class="grid grid-cols-[repeat(auto-fill,minmax(160px,1fr))] gap-y-3 gap-x-5 pt-1 pb-[18px] pl-[22px]">
                                @foreach($grupo['campos'] as $fila)
                                    <x-cp.valor :campo="$fila['campo']" :valor="$fila['valor']" />
                                @endforeach
                            </div>
                        </details>
                    @endforeach
                </div>
            @endif

            {{-- ---- Compromisos ---- --}}
            <div x-show="tab === 'compromisos'" x-cloak class="p-5 flex flex-col gap-6">
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
    </main>

    {{-- ================= DERECHA: registrar gestión ================= --}}
    <aside class="vt-col-right card flex flex-col min-h-0 2xl:overflow-hidden">
        @if($casoActivo && $puedeGestionarCaso)
            <div class="px-[18px] pt-3.5 pb-3 border-b border-ink-200 flex items-center justify-between gap-2 shrink-0">
                <h3 class="text-md font-semibold text-ink">{{ __('casos.register_gestion_title') }}</h3>
                <span class="font-mono text-xs text-ink-500 truncate">{{ $casoActivo->referencia }}</span>
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
