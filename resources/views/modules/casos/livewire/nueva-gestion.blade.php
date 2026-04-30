<div class="bg-white border border-gray-200 rounded-lg p-4"
     x-data
     @keydown.ctrl.enter.window="$wire.guardar()">

    <div class="flex items-center justify-between">
        <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-700">Nueva gestión</h3>
        @if(session('nueva-gestion-ok'))
            <div class="text-xs text-emerald-700 bg-emerald-50 border border-emerald-200 rounded px-2 py-1"
                 x-data="{show:true}" x-show="show" x-init="setTimeout(()=>show=false, 3000)">
                {{ session('nueva-gestion-ok') }}
            </div>
        @endif
    </div>

    @error('general')<div class="mt-2 text-xs text-red-700 bg-red-50 border border-red-200 rounded px-2 py-1">{{ $message }}</div>@enderror

    <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <div>
            <label class="block text-xs font-medium text-gray-700">Canal</label>
            <select wire:model.live="canalId"
                    class="mt-1 block w-full text-sm rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                <option value="">—</option>
                @foreach($canales as $c)
                    <option value="{{ $c->id }}">{{ $c->nombre }}</option>
                @endforeach
            </select>
            @error('canalId')<div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>@enderror
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-700">Tipo de gestión</label>
            <select wire:model.live="tipoGestionId"
                    class="mt-1 block w-full text-sm rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                <option value="">—</option>
                @foreach($tiposGestion as $t)
                    <option value="{{ $t->id }}">{{ $t->nombre }}</option>
                @endforeach
            </select>
            @error('tipoGestionId')<div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>@enderror
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-700">Resultado</label>
            <select wire:model.live="resultadoId"
                    class="mt-1 block w-full text-sm rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                <option value="">—</option>
                @foreach($resultados as $r)
                    <option value="{{ $r->id }}">{{ $r->nombre }}</option>
                @endforeach
            </select>
            @error('resultadoId')<div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>@enderror
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-700">Contacto usado</label>
            <select wire:model="contactoId"
                    class="mt-1 block w-full text-sm rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                <option value="">—</option>
                @foreach($contactos as $co)
                    <option value="{{ $co->id }}">{{ ucfirst($co->tipo) }} · {{ $co->valor }}</option>
                @endforeach
            </select>
        </div>

        @if(! $esContactoEfectivo && $resultadoId)
            <div>
                <label class="block text-xs font-medium text-gray-700">Motivo no contacto</label>
                <select wire:model="motivoNoContactoId"
                        class="mt-1 block w-full text-sm rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                    <option value="">—</option>
                    @foreach($motivos as $m)
                        <option value="{{ $m->id }}">{{ $m->nombre }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        @if($requiereCausa)
            <div>
                <label class="block text-xs font-medium text-gray-700">
                    Causa <span class="text-red-600">*</span>
                </label>
                <select wire:model="causaId"
                        class="mt-1 block w-full text-sm rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                    <option value="">—</option>
                    @foreach($causas as $ca)
                        <option value="{{ $ca->id }}">{{ $ca->nombre }}</option>
                    @endforeach
                </select>
                @error('causaId')<div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>@enderror
            </div>
        @endif

        <div>
            <label class="block text-xs font-medium text-gray-700">Duración (seg)</label>
            <input type="number" min="0" step="1" wire:model="duracionSegundos"
                   class="mt-1 block w-full text-sm rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500"/>
        </div>
    </div>

    <div class="mt-3">
        <label class="block text-xs font-medium text-gray-700">Notas (opcional)</label>
        <textarea wire:model="notas" rows="2"
                  class="mt-1 block w-full text-sm rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                  placeholder="Complemento libre. No extraigas datos de aquí, usa los campos estructurados."></textarea>
    </div>

    @if($requiereCompromiso && $tipoCaso === 'cobranza')
        <div class="mt-3 rounded-md border border-amber-200 bg-amber-50 p-3">
            <div class="text-xs font-semibold uppercase tracking-wider text-amber-800">Promesa de pago</div>
            <div class="mt-2 grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-medium text-amber-900">
                        Monto USD <span class="text-red-600">*</span>
                    </label>
                    <input type="text" wire:model="promesaMonto" placeholder="0.00"
                           class="mt-1 block w-full text-sm rounded border-amber-300 focus:border-amber-500 focus:ring-amber-500"/>
                    @error('promesaMonto')<div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-amber-900">
                        Fecha <span class="text-red-600">*</span>
                    </label>
                    <input type="date" wire:model="promesaFecha"
                           class="mt-1 block w-full text-sm rounded border-amber-300 focus:border-amber-500 focus:ring-amber-500"/>
                    @error('promesaFecha')<div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-amber-900">Tipo de pago</label>
                    <select wire:model="promesaTipoPagoId"
                            class="mt-1 block w-full text-sm rounded border-amber-300 focus:border-amber-500 focus:ring-amber-500">
                        <option value="">—</option>
                        @foreach($tiposPago as $tp)
                            <option value="{{ $tp->id }}">{{ $tp->nombre }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    @endif

    @if($requiereCompromiso && $tipoCaso === 'lead_venta')
        <div class="mt-3 rounded-md border border-emerald-200 bg-emerald-50 p-3">
            <div class="text-xs font-semibold uppercase tracking-wider text-emerald-800">Promesa de cierre</div>
            <div class="mt-2 grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-medium text-emerald-900">
                        Monto USD <span class="text-red-600">*</span>
                    </label>
                    <input type="text" wire:model="cierreMonto" placeholder="0.00"
                           class="mt-1 block w-full text-sm rounded border-emerald-300 focus:border-emerald-500 focus:ring-emerald-500"/>
                    @error('cierreMonto')<div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-emerald-900">
                        Fecha estimada <span class="text-red-600">*</span>
                    </label>
                    <input type="date" wire:model="cierreFechaEstimada"
                           class="mt-1 block w-full text-sm rounded border-emerald-300 focus:border-emerald-500 focus:ring-emerald-500"/>
                    @error('cierreFechaEstimada')<div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-emerald-900">Etapa del embudo</label>
                    <select wire:model="cierreEtapaEmbudoId"
                            class="mt-1 block w-full text-sm rounded border-emerald-300 focus:border-emerald-500 focus:ring-emerald-500">
                        <option value="">—</option>
                        @foreach($etapasEmbudo as $ee)
                            <option value="{{ $ee->id }}">{{ $ee->nombre }} ({{ $ee->probabilidad_cierre }}%)</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    @endif

    @if($requiereCompromiso && $tipoCaso === 'servicio')
        <div class="mt-3 rounded-md border border-blue-200 bg-blue-50 p-3">
            <div class="text-xs font-semibold uppercase tracking-wider text-blue-800">Acción de servicio programada</div>
            <div class="mt-2 grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-blue-900">
                        Descripción de la acción <span class="text-red-600">*</span>
                    </label>
                    <input type="text" wire:model="accionDescripcion" maxlength="500"
                           placeholder="Ej. Instalación de equipos en domicilio"
                           class="mt-1 block w-full text-sm rounded border-blue-300 focus:border-blue-500 focus:ring-blue-500"/>
                    @error('accionDescripcion')<div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-blue-900">
                        Fecha programada <span class="text-red-600">*</span>
                    </label>
                    <input type="datetime-local" wire:model="accionFechaProgramada"
                           class="mt-1 block w-full text-sm rounded border-blue-300 focus:border-blue-500 focus:ring-blue-500"/>
                    @error('accionFechaProgramada')<div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-blue-900">Tipo de acción</label>
                    <select wire:model="accionTipoAccionId"
                            class="mt-1 block w-full text-sm rounded border-blue-300 focus:border-blue-500 focus:ring-blue-500">
                        <option value="">—</option>
                        @foreach($tiposAccionServicio as $ta)
                            <option value="{{ $ta->id }}">{{ $ta->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-blue-900">Técnico asignado</label>
                    <input type="text" wire:model="accionTecnicoAsignado" maxlength="150"
                           placeholder="Nombre del técnico"
                           class="mt-1 block w-full text-sm rounded border-blue-300 focus:border-blue-500 focus:ring-blue-500"/>
                </div>
            </div>
        </div>
    @endif

    @if($requiereCompromiso && $tipoCaso === 'ticket_cx')
        <div class="mt-3 rounded-md border border-sky-200 bg-sky-50 p-3">
            <div class="text-xs font-semibold uppercase tracking-wider text-sky-800">Resolución / Escalamiento</div>
            <div class="mt-2 grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-sky-900">
                        Acción comprometida <span class="text-red-600">*</span>
                    </label>
                    <input type="text" wire:model="resolucionAccion" maxlength="500"
                           placeholder="Ej. Revisar facturación y llamar al cliente"
                           class="mt-1 block w-full text-sm rounded border-sky-300 focus:border-sky-500 focus:ring-sky-500"/>
                    @error('resolucionAccion')<div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-sky-900">
                        Fecha límite <span class="text-red-600">*</span>
                    </label>
                    <input type="datetime-local" wire:model="resolucionFechaLimite"
                           class="mt-1 block w-full text-sm rounded border-sky-300 focus:border-sky-500 focus:ring-sky-500"/>
                    @error('resolucionFechaLimite')<div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>@enderror
                </div>
                <div class="sm:col-span-3">
                    <label class="block text-xs font-medium text-sky-900">Nivel escalamiento</label>
                    <select wire:model="resolucionNivelEscalamientoId"
                            class="mt-1 block w-full text-sm rounded border-sky-300 focus:border-sky-500 focus:ring-sky-500">
                        <option value="">—</option>
                        @foreach($nivelesEscalamiento as $ne)
                            <option value="{{ $ne->id }}">{{ $ne->nombre }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    @endif

    <div class="mt-4 flex items-center justify-between">
        <div class="text-[10px] text-gray-500">Ctrl+Enter para guardar.</div>
        <button type="button" wire:click="guardar"
                class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
            Registrar gestión
        </button>
    </div>
</div>
