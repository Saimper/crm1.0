<div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

    {{-- Identidad de la persona --}}
    <section class="bg-white shadow rounded-lg p-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <div class="text-xs uppercase tracking-wider text-gray-500">
                    {{ $persona->tipo_identificacion_codigo ?? 'ID' }} · {{ $persona->identificacion }}
                </div>
                <h1 class="mt-1 text-2xl font-semibold text-gray-900">
                    {{ $nombrePersona !== '' ? $nombrePersona : '—' }}
                </h1>
                <div class="mt-1 text-xs text-gray-500">
                    {{ ucfirst($persona->tipo_persona) }}
                    @if($persona->tipo_persona === 'fisica' && $persona->fecha_nacimiento)
                        · nac. {{ \Illuminate\Support\Carbon::parse($persona->fecha_nacimiento)->format('d/m/Y') }}
                    @endif
                </div>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('proyectos.personas.contactos', ['proyecto_id' => $proyectoActivo->id, 'persona' => $persona->public_id]) }}"
                   wire:navigate
                   class="inline-flex items-center px-3 py-1.5 border border-gray-300 bg-white text-gray-700 text-xs font-medium rounded-md hover:bg-gray-50">
                    Contactos
                </a>
                <a href="{{ route('proyectos.bandeja', ['proyecto_id' => $proyectoActivo->id]) }}"
                   wire:navigate
                   class="inline-flex items-center px-3 py-1.5 border border-gray-300 bg-white text-gray-700 text-xs font-medium rounded-md hover:bg-gray-50">
                    Volver a bandeja
                </a>
            </div>
        </div>

        @if($contactos->isNotEmpty())
            <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 text-xs">
                @foreach($contactos as $c)
                    <div class="rounded border border-gray-200 px-3 py-2">
                        <div class="flex items-center justify-between">
                            <span class="font-medium text-gray-800">{{ ucfirst($c->tipo) }}</span>
                            @if($c->es_principal)
                                <span class="text-[10px] uppercase text-indigo-700 font-semibold">principal</span>
                            @endif
                        </div>
                        <div class="text-gray-700 break-all">{{ $c->valor }}</div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    {{-- Selector de casos --}}
    <section class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200 bg-gray-50 text-xs font-semibold uppercase tracking-wider text-gray-600">
            Casos ({{ $casos->count() }})
        </div>

        @if($casos->isEmpty())
            <div class="p-6 text-sm text-gray-500 text-center">
                Esta persona aún no tiene casos abiertos en este proyecto.
            </div>
        @else
            <div class="divide-y divide-gray-100">
                @foreach($casos as $c)
                    @php
                        $activo = $casoActivo && $c->public_id === $casoActivo->public_id;
                        $tipoColor = match ($c->tipo_caso) {
                            'cobranza'   => 'bg-amber-100 text-amber-800',
                            'ticket_cx'  => 'bg-sky-100 text-sky-800',
                            'lead_venta' => 'bg-emerald-100 text-emerald-800',
                            'servicio'   => 'bg-violet-100 text-violet-800',
                            default      => 'bg-gray-100 text-gray-700',
                        };
                    @endphp
                    <button type="button"
                            wire:click="seleccionarCaso('{{ $c->public_id }}')"
                            class="w-full text-left px-4 py-3 flex items-center gap-3 hover:bg-gray-50 {{ $activo ? 'bg-indigo-50' : '' }}">
                        <span class="inline-block rounded px-2 py-0.5 text-xs font-medium {{ $tipoColor }}">
                            {{ ucfirst(str_replace('_', ' ', $c->tipo_caso)) }}
                        </span>
                        <div class="flex-1">
                            <div class="text-sm font-medium text-gray-900">{{ $c->cartera_nombre }}</div>
                            <div class="text-xs text-gray-500">
                                {{ $c->estado_caso_nombre }}
                                @if($c->cerrado_en) · cerrado {{ \Illuminate\Support\Carbon::parse($c->cerrado_en)->format('d/m/Y') }}@endif
                                @if($c->tiene_compromiso_vigente)
                                    · <span class="text-emerald-700 font-semibold">compromiso vigente</span>
                                @endif
                            </div>
                        </div>
                        <div class="text-xs text-gray-500 text-right">
                            prio {{ $c->prioridad }}
                            <div class="text-[10px]">ingreso {{ \Illuminate\Support\Carbon::parse($c->fecha_ingreso)->format('d/m/Y') }}</div>
                        </div>
                    </button>
                @endforeach
            </div>
        @endif
    </section>

    {{-- Panel del caso activo --}}
    @if($casoActivo)
        <section class="bg-white shadow rounded-lg p-6 space-y-4">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-xs uppercase tracking-wider text-gray-500">Caso seleccionado</div>
                    <h2 class="mt-1 text-lg font-semibold text-gray-900">
                        {{ $casoActivo->cartera_nombre }} · {{ ucfirst(str_replace('_', ' ', $casoActivo->tipo_caso)) }}
                    </h2>
                    <div class="text-xs text-gray-500">
                        estado {{ $casoActivo->estado_caso_nombre }}
                    </div>
                </div>

                @if($compromisoActivo)
                    <div class="rounded border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-800 min-w-[220px]">
                        <div class="font-semibold uppercase tracking-wider">Compromiso vigente</div>
                        <div>
                            {{ \Illuminate\Support\Carbon::parse($compromisoActivo->fecha_vencimiento)->format('d/m/Y') }}
                        </div>
                        @if(isset($compromisoActivo->promesa) && $compromisoActivo->promesa)
                            <div class="mt-1 font-semibold">
                                {{ $compromisoActivo->promesa->moneda }}
                                {{ number_format((float) $compromisoActivo->promesa->monto, 2, '.', ',') }}
                            </div>
                        @endif
                        @if(isset($compromisoActivo->resolucion) && $compromisoActivo->resolucion)
                            <div class="mt-1 text-[11px] text-emerald-900/80">
                                {{ $compromisoActivo->resolucion->accion_comprometida }}
                                @if($compromisoActivo->resolucion->escalamiento_nombre)
                                    <div class="text-[10px]">· {{ $compromisoActivo->resolucion->escalamiento_nombre }}</div>
                                @endif
                            </div>
                        @endif
                        @if(isset($compromisoActivo->cierre) && $compromisoActivo->cierre)
                            <div class="mt-1 font-semibold">
                                Cierre {{ $compromisoActivo->cierre->moneda }}
                                {{ number_format((float) $compromisoActivo->cierre->monto_cierre, 2, '.', ',') }}
                                @if($compromisoActivo->cierre->etapa_nombre)
                                    <span class="text-[10px]">· {{ $compromisoActivo->cierre->etapa_nombre }}</span>
                                @endif
                            </div>
                        @endif
                        @if(isset($compromisoActivo->accion) && $compromisoActivo->accion)
                            <div class="mt-1 text-[11px] text-emerald-900/80">
                                {{ $compromisoActivo->accion->descripcion_accion }}
                                @if($compromisoActivo->accion->tipo_accion_nombre)
                                    <div class="text-[10px]">· {{ $compromisoActivo->accion->tipo_accion_nombre }}</div>
                                @endif
                                @if($compromisoActivo->accion->tecnico_asignado)
                                    <div class="text-[10px]">· {{ $compromisoActivo->accion->tecnico_asignado }}</div>
                                @endif
                            </div>
                        @endif
                        @if($casoActivo->tipo_caso === 'cobranza' && $compromisoActivo->tipo_compromiso === 'promesa_pago')
                            <div class="mt-2">
                                <livewire:cobranza.resolver-promesa
                                    :compromisoId="$compromisoActivo->id"
                                    :key="'resolver-promesa-'.$compromisoActivo->id" />
                            </div>
                        @endif
                        @if($casoActivo->tipo_caso === 'ticket_cx' && $compromisoActivo->tipo_compromiso === 'resolucion_ticket')
                            <div class="mt-2">
                                <livewire:cx.resolver-resolucion
                                    :compromisoId="$compromisoActivo->id"
                                    :key="'resolver-resolucion-'.$compromisoActivo->id" />
                            </div>
                        @endif
                        @if($casoActivo->tipo_caso === 'lead_venta' && $compromisoActivo->tipo_compromiso === 'cierre_venta')
                            <div class="mt-2">
                                <livewire:venta.resolver-cierre
                                    :compromisoId="$compromisoActivo->id"
                                    :key="'resolver-cierre-'.$compromisoActivo->id" />
                            </div>
                        @endif
                        @if($casoActivo->tipo_caso === 'servicio' && $compromisoActivo->tipo_compromiso === 'accion_servicio')
                            <div class="mt-2">
                                <livewire:servicio.resolver-accion
                                    :compromisoId="$compromisoActivo->id"
                                    :key="'resolver-accion-'.$compromisoActivo->id" />
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Slot tipo-específico: datos del caso --}}
            @if($casoActivo->tipo_caso === 'cobranza')
                @include('cobranza::partials.panel-caso', ['cobranza' => $casoCobranza])
            @elseif($casoActivo->tipo_caso === 'ticket_cx')
                @include('cx::partials.panel-caso', ['ticket' => $casoTicketCx])
            @elseif($casoActivo->tipo_caso === 'lead_venta')
                @include('venta::partials.panel-caso', ['lead' => $casoLeadVenta])
            @elseif($casoActivo->tipo_caso === 'servicio')
                @include('servicio::partials.panel-caso', ['servicio' => $casoServicio])
            @endif

            {{-- Campos personalizados del caso × cartera --}}
            <livewire:campos-personalizados.formulario
                :proyectoId="(int) $proyectoActivo->id"
                ambito="caso"
                :ambitoId="(int) ($casoActivo->cartera_id ?? 0)"
                :entidadId="(int) $casoActivo->id"
                :key="'cp-caso-'.$casoActivo->id" />

            {{-- Formulario de nueva gestión --}}
            <livewire:casos.nueva-gestion
                :casoId="$casoActivo->id"
                :personaId="$persona->id"
                :tipoCaso="$casoActivo->tipo_caso"
                :key="'nueva-gestion-'.$casoActivo->id" />

            {{-- Historial de gestiones --}}
            <div>
                <div class="text-xs font-semibold uppercase tracking-wider text-gray-600 mb-2">
                    Historial ({{ $historial->count() }})
                </div>

                @if($historial->isEmpty())
                    <div class="rounded border border-dashed border-gray-300 bg-gray-50 p-6 text-center text-xs text-gray-500">
                        Sin gestiones registradas.
                    </div>
                @else
                    <ul class="divide-y divide-gray-100 border border-gray-200 rounded-md overflow-hidden">
                        @foreach($historial as $g)
                            <li class="px-3 py-2 text-sm bg-white">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="font-medium text-gray-900">
                                            {{ $g->resultado_nombre ?? '—' }}
                                            <span class="text-xs text-gray-500">· {{ $g->tipo_gestion_nombre ?? '—' }}</span>
                                            <span class="text-xs text-gray-400">· {{ $g->canal_nombre ?? '—' }}</span>
                                        </div>
                                        @if($g->notas)
                                            <div class="text-xs text-gray-700 mt-0.5">{{ $g->notas }}</div>
                                        @endif
                                        <div class="text-[10px] text-gray-500 mt-0.5">
                                            {{ $g->usuario_nombre ?? '—' }}
                                            @if($g->duracion_segundos)
                                                · {{ (int) floor($g->duracion_segundos / 60) }}m {{ $g->duracion_segundos % 60 }}s
                                            @endif
                                        </div>
                                    </div>
                                    <div class="text-xs text-gray-500 whitespace-nowrap">
                                        {{ \Illuminate\Support\Carbon::parse($g->creada_en)->diffForHumans() }}
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

        </section>
    @endif
</div>
