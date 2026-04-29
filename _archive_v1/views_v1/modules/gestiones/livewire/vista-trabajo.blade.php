<div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4" wire:key="vista-trabajo-{{ $cliente->public_id }}-{{ $productoActivo?->public_id }}">

    {{-- Identidad del cliente --}}
    <section class="bg-white shadow rounded-lg p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="text-xs uppercase text-gray-500 tracking-wider">
                    {{ $cliente->tipo_persona === 'juridica' ? 'Empresa' : 'Persona física' }}
                </div>
                <h1 class="text-2xl font-bold text-gray-900">{{ $nombreCliente }}</h1>
                <div class="mt-1 text-sm text-gray-600">{{ $cliente->identificacion }}</div>
            </div>

            @if($contactos->isNotEmpty())
                <div class="text-sm text-gray-700 max-w-md">
                    <div class="text-xs uppercase text-gray-500 tracking-wider mb-1">Contactos</div>
                    <ul class="space-y-0.5">
                        @foreach($contactos->take(4) as $c)
                            <li class="flex items-center gap-2">
                                <span class="inline-block rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-700">{{ ucfirst($c->tipo) }}</span>
                                <span class="text-gray-800">{{ $c->valor }}</span>
                                @if($c->es_principal)
                                    <span class="text-[10px] uppercase text-emerald-700">principal</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </section>

    {{-- Pestañas de productos --}}
    @if($productos->isEmpty())
        <section class="bg-white shadow rounded-lg p-6 text-gray-600">
            Este cliente no tiene productos registrados.
        </section>
    @else
        <section class="bg-white shadow rounded-lg overflow-hidden">
            <nav class="flex border-b border-gray-200 overflow-x-auto">
                @foreach($productos as $p)
                    @php
                        $activo = $p->public_id === $productoActivo?->public_id;
                        $morado = (int) $p->dias_mora;
                    @endphp
                    <button type="button"
                            wire:click="seleccionarProducto('{{ $p->public_id }}')"
                            class="flex-shrink-0 px-4 py-3 text-sm border-b-2 transition {{ $activo
                                ? 'border-indigo-600 text-indigo-700'
                                : 'border-transparent text-gray-600 hover:text-gray-900' }}">
                        <div class="font-medium">{{ $p->numero_prestamo }}</div>
                        <div class="text-xs mt-0.5 {{ $morado > 0 ? 'text-amber-700' : 'text-emerald-700' }}">
                            {{ $morado > 0 ? $morado.' días mora' : 'al día' }}
                        </div>
                    </button>
                @endforeach
            </nav>

            @if($productoActivo)
                <div class="p-6 space-y-4">
                    {{-- Datos financieros --}}
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                        <div>
                            <div class="text-xs uppercase text-gray-500 tracking-wider">Saldo total</div>
                            <div class="font-semibold text-gray-900">{{ $productoActivo->moneda }} {{ number_format((float) $productoActivo->saldo_total, 2) }}</div>
                        </div>
                        <div>
                            <div class="text-xs uppercase text-gray-500 tracking-wider">Saldo capital</div>
                            <div class="font-semibold text-gray-900">{{ $productoActivo->moneda }} {{ number_format((float) $productoActivo->saldo_capital, 2) }}</div>
                        </div>
                        <div>
                            <div class="text-xs uppercase text-gray-500 tracking-wider">Cuota mensual</div>
                            <div class="font-semibold text-gray-900">{{ $productoActivo->moneda }} {{ number_format((float) $productoActivo->cuota_mensual, 2) }}</div>
                        </div>
                        <div>
                            <div class="text-xs uppercase text-gray-500 tracking-wider">Estado</div>
                            <div class="font-semibold text-gray-900">{{ $productoActivo->estado_nombre ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-xs uppercase text-gray-500 tracking-wider">Tramo de mora</div>
                            <div class="text-gray-800">{{ $productoActivo->tramo_nombre ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-xs uppercase text-gray-500 tracking-wider">Cartera</div>
                            <div class="text-gray-800">{{ $productoActivo->cartera_nombre ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-xs uppercase text-gray-500 tracking-wider">Cuotas</div>
                            <div class="text-gray-800">{{ $productoActivo->cuotas_pagadas }} / {{ $productoActivo->cuotas_totales }}</div>
                        </div>
                        <div>
                            <div class="text-xs uppercase text-gray-500 tracking-wider">Última gestión</div>
                            <div class="text-gray-800">
                                {{ $productoActivo->fecha_ultima_gestion
                                    ? \Illuminate\Support\Carbon::parse($productoActivo->fecha_ultima_gestion)->diffForHumans()
                                    : 'sin gestiones' }}
                            </div>
                        </div>
                    </div>

                    {{-- Promesa vigente --}}
                    @if($promesaVigente)
                        <div class="rounded-md border border-emerald-300 bg-emerald-50 px-4 py-3 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <div class="text-xs uppercase tracking-wider text-emerald-800">Promesa vigente</div>
                                <div class="text-sm text-emerald-900 mt-0.5">
                                    {{ $productoActivo->moneda }} {{ number_format((float) $promesaVigente->monto_promesa, 2) }}
                                    · vence {{ \Illuminate\Support\Carbon::parse($promesaVigente->fecha_promesa)->format('d M Y') }}
                                </div>
                            </div>
                            <livewire:promesas.resolver-promesa
                                :promesa-id="$promesaVigente->id"
                                :monto="(string) $promesaVigente->monto_promesa"
                                :moneda="$productoActivo->moneda"
                                :fecha-promesa="(string) $promesaVigente->fecha_promesa"
                                :key="'resolver-promesa-'.$promesaVigente->id" />
                        </div>
                    @endif

                    {{-- Formulario de nueva gestión --}}
                    <div class="rounded-md border border-gray-200 bg-gray-50 p-4">
                        <div class="text-xs font-semibold uppercase tracking-wider text-gray-700 mb-3">Nueva gestión</div>
                        <livewire:gestiones.nueva-gestion
                            :producto-id="$productoActivo->id"
                            :cliente-id="$cliente->id"
                            :key="'form-gestion-'.$productoActivo->id" />
                    </div>
                </div>
            @endif
        </section>

        {{-- Historial --}}
        @if($productoActivo)
            <section class="bg-white shadow rounded-lg">
                <header class="px-6 py-3 border-b border-gray-200 flex items-baseline justify-between">
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-700">
                        Historial de gestiones
                    </h2>
                    <span class="text-xs text-gray-500">últimas {{ $historial->count() }}</span>
                </header>

                @if($historial->isEmpty())
                    <div class="p-6 text-sm text-gray-600">Sin gestiones registradas para este producto.</div>
                @else
                    <ul class="divide-y divide-gray-200">
                        @foreach($historial as $g)
                            @php
                                $meta = is_string($g->resultado_metadata) ? json_decode($g->resultado_metadata, true) : ($g->resultado_metadata ?? []);
                                $efectivo = (bool) ($meta['es_contacto_efectivo'] ?? false);
                            @endphp
                            <li class="px-6 py-3 text-sm">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium {{ $efectivo
                                                ? 'bg-emerald-100 text-emerald-800'
                                                : 'bg-gray-100 text-gray-700' }}">
                                                {{ $g->resultado_nombre ?? $g->resultado_codigo }}
                                            </span>
                                            <span class="text-xs text-gray-600">
                                                {{ $g->tipo_gestion_nombre }} · {{ $g->canal_nombre }}
                                            </span>
                                        </div>
                                        @if($g->causa_mora_nombre)
                                            <div class="mt-0.5 text-xs text-gray-600">Causa: {{ $g->causa_mora_nombre }}</div>
                                        @endif
                                        @if($g->notas)
                                            <div class="mt-1 text-gray-800">{{ $g->notas }}</div>
                                        @endif
                                    </div>
                                    <div class="text-right text-xs text-gray-600 flex-shrink-0">
                                        <div>{{ \Illuminate\Support\Carbon::parse($g->creada_en)->format('d M Y H:i') }}</div>
                                        <div>{{ $g->usuario_nombre }}</div>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endif
    @endif
</div>
