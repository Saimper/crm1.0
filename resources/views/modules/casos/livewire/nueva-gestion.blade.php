<div class="bg-white border border-ink-200 rounded-lg p-4"
     x-data
     @keydown.ctrl.enter.window="$wire.guardar()">

    <div class="flex items-center justify-between">
        <h3 class="text-sm font-semibold uppercase tracking-wider text-ink-700">{{ __('casos.gestion_title') }}</h3>
        @if(session('nueva-gestion-ok'))
            <div class="text-xs text-success-700 bg-success-50 border border-success-200 rounded px-2 py-1"
                 x-data="{show:true}" x-show="show" x-init="setTimeout(()=>show=false, 3000)">
                {{ session('nueva-gestion-ok') }}
            </div>
        @endif
    </div>

    @error('general')<div class="mt-2 text-xs text-danger-700 bg-danger-50 border border-danger-200 rounded px-2 py-1">{{ $message }}</div>@enderror

    <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <div>
            <label class="block text-xs font-medium text-ink-700">{{ __('casos.field_channel') }}</label>
            <select wire:model.live="canalId"
                    class="mt-1 block w-full text-sm rounded border-ink-300 focus:border-brand-500 focus:ring-brand-500">
                <option value="">—</option>
                @foreach($canales as $c)
                    <option value="{{ $c->id }}">{{ $c->nombre }}</option>
                @endforeach
            </select>
            @error('canalId')<div class="text-xs text-danger-600 mt-0.5">{{ $message }}</div>@enderror
        </div>

        <div>
            <label class="block text-xs font-medium text-ink-700">{{ __('casos.field_gestion_type') }}</label>
            <select wire:model.live="tipoGestionId"
                    class="mt-1 block w-full text-sm rounded border-ink-300 focus:border-brand-500 focus:ring-brand-500">
                <option value="">—</option>
                @foreach($tiposGestion as $t)
                    <option value="{{ $t->id }}">{{ $t->nombre }}</option>
                @endforeach
            </select>
            @error('tipoGestionId')<div class="text-xs text-danger-600 mt-0.5">{{ $message }}</div>@enderror
        </div>

        <div>
            <label class="block text-xs font-medium text-ink-700">{{ __('casos.field_result') }}</label>
            <select wire:model.live="resultadoId"
                    class="mt-1 block w-full text-sm rounded border-ink-300 focus:border-brand-500 focus:ring-brand-500">
                <option value="">—</option>
                @foreach($resultados as $r)
                    <option value="{{ $r->id }}">{{ $r->nombre }}</option>
                @endforeach
            </select>
            @error('resultadoId')<div class="text-xs text-danger-600 mt-0.5">{{ $message }}</div>@enderror
        </div>

        <div>
            <label class="block text-xs font-medium text-ink-700">{{ __('casos.field_contact_used') }}</label>
            <select wire:model="contactoId"
                    class="mt-1 block w-full text-sm rounded border-ink-300 focus:border-brand-500 focus:ring-brand-500">
                <option value="">—</option>
                @foreach($contactos as $co)
                    <option value="{{ $co->id }}">{{ ucfirst($co->tipo) }} · {{ $co->valor }}</option>
                @endforeach
            </select>
        </div>

        @if(! $esContactoEfectivo && $resultadoId)
            <div>
                <label class="block text-xs font-medium text-ink-700">{{ __('casos.field_no_contact_reason') }}</label>
                <select wire:model="motivoNoContactoId"
                        class="mt-1 block w-full text-sm rounded border-ink-300 focus:border-brand-500 focus:ring-brand-500">
                    <option value="">—</option>
                    @foreach($motivos as $m)
                        <option value="{{ $m->id }}">{{ $m->nombre }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        @if($requiereCausa)
            <div>
                <label class="block text-xs font-medium text-ink-700">
                    {{ __('casos.field_cause') }} <span class="text-danger-600">*</span>
                </label>
                <select wire:model="causaId"
                        class="mt-1 block w-full text-sm rounded border-ink-300 focus:border-brand-500 focus:ring-brand-500">
                    <option value="">—</option>
                    @foreach($causas as $ca)
                        <option value="{{ $ca->id }}">{{ $ca->nombre }}</option>
                    @endforeach
                </select>
                @error('causaId')<div class="text-xs text-danger-600 mt-0.5">{{ $message }}</div>@enderror
            </div>
        @endif

        <div>
            <label class="block text-xs font-medium text-ink-700">{{ __('casos.field_duration') }}</label>
            <input type="number" min="0" step="1" wire:model="duracionSegundos"
                   class="mt-1 block w-full text-sm rounded border-ink-300 focus:border-brand-500 focus:ring-brand-500"/>
        </div>
    </div>

    <div class="mt-3">
        <label class="block text-xs font-medium text-ink-700">{{ __('casos.field_notes') }}</label>
        <textarea wire:model="notas" rows="2"
                  class="mt-1 block w-full text-sm rounded border-ink-300 focus:border-brand-500 focus:ring-brand-500"
                  placeholder="{{ __('casos.notes_placeholder') }}"></textarea>
    </div>

    {{-- Campos personalizados ámbito gestion × tipo_gestion. Solo aparecen cuando
         el tipo seleccionado tiene definiciones; se persisten junto a la gestión. --}}
    @if($tipoGestionId && $camposGestion->isNotEmpty())
        <div class="mt-4 pt-3" style="border-top:1px solid var(--border);">
            <h4 class="text-xs font-semibold uppercase tracking-wider mb-2" style="color:var(--text-secondary);letter-spacing:0.06em;">
                {{ __('casos.custom_fields_title') }}
            </h4>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach($camposGestion as $campo)
                    <div>
                        <label class="block text-xs font-medium text-ink-700">
                            {{ $campo->etiqueta }}
                            @if($campo->obligatorio)<span class="text-danger-600">*</span>@endif
                        </label>

                        @switch($campo->tipo)
                            @case('texto_corto')
                                <input type="text" wire:model="valoresCamposGestion.{{ $campo->codigo }}"
                                       class="mt-1 block w-full text-sm rounded border-ink-300 focus:border-brand-500 focus:ring-brand-500"/>
                                @break
                            @case('texto_largo')
                                <textarea wire:model="valoresCamposGestion.{{ $campo->codigo }}" rows="2"
                                          class="mt-1 block w-full text-sm rounded border-ink-300 focus:border-brand-500 focus:ring-brand-500"></textarea>
                                @break
                            @case('numero_entero')
                                <input type="number" step="1" wire:model="valoresCamposGestion.{{ $campo->codigo }}"
                                       class="mt-1 block w-full text-sm rounded border-ink-300 focus:border-brand-500 focus:ring-brand-500"/>
                                @break
                            @case('numero_decimal')
                            @case('moneda')
                                <input type="text" wire:model="valoresCamposGestion.{{ $campo->codigo }}" placeholder="0.00"
                                       class="mt-1 block w-full text-sm rounded border-ink-300 focus:border-brand-500 focus:ring-brand-500"/>
                                @break
                            @case('fecha')
                                <input type="date" wire:model="valoresCamposGestion.{{ $campo->codigo }}"
                                       class="mt-1 block w-full text-sm rounded border-ink-300 focus:border-brand-500 focus:ring-brand-500"/>
                                @break
                            @case('fecha_hora')
                                <input type="datetime-local" wire:model="valoresCamposGestion.{{ $campo->codigo }}"
                                       class="mt-1 block w-full text-sm rounded border-ink-300 focus:border-brand-500 focus:ring-brand-500"/>
                                @break
                            @case('booleano')
                                <select wire:model="valoresCamposGestion.{{ $campo->codigo }}"
                                        class="mt-1 block w-full text-sm rounded border-ink-300 focus:border-brand-500 focus:ring-brand-500">
                                    <option value="">—</option>
                                    <option value="1">{{ __('casos.yes') }}</option>
                                    <option value="0">{{ __('casos.no') }}</option>
                                </select>
                                @break
                            @default
                                <input type="text" wire:model="valoresCamposGestion.{{ $campo->codigo }}"
                                       class="mt-1 block w-full text-sm rounded border-ink-300 focus:border-brand-500 focus:ring-brand-500"/>
                        @endswitch
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Campos personalizados ámbito caso × cartera. Siempre visibles si el caso tiene definiciones. --}}
    @if($camposCaso->isNotEmpty())
        <div class="mt-4 pt-3" style="border-top:1px solid var(--border);">
            <h4 class="text-xs font-semibold uppercase tracking-wider mb-2" style="color:var(--text-secondary);letter-spacing:0.06em;">
                {{ __('casos.case_fields_title') }}
            </h4>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach($camposCaso as $campo)
                    <div>
                        <label class="block text-xs font-medium text-ink-700">
                            {{ $campo->etiqueta }}
                            @if($campo->obligatorio)<span class="text-danger-600">*</span>@endif
                        </label>
                        @switch($campo->tipo)
                            @case('texto_corto')
                                <input type="text" wire:model="valoresCamposCaso.{{ $campo->codigo }}"
                                       class="mt-1 block w-full text-sm rounded border-ink-300 focus:border-brand-500 focus:ring-brand-500"/>
                                @break
                            @case('texto_largo')
                                <textarea wire:model="valoresCamposCaso.{{ $campo->codigo }}" rows="2"
                                          class="mt-1 block w-full text-sm rounded border-ink-300 focus:border-brand-500 focus:ring-brand-500"></textarea>
                                @break
                            @case('numero_entero')
                                <input type="number" step="1" wire:model="valoresCamposCaso.{{ $campo->codigo }}"
                                       class="mt-1 block w-full text-sm rounded border-ink-300 focus:border-brand-500 focus:ring-brand-500"/>
                                @break
                            @case('numero_decimal')
                            @case('moneda')
                                <input type="text" wire:model="valoresCamposCaso.{{ $campo->codigo }}" placeholder="0.00"
                                       class="mt-1 block w-full text-sm rounded border-ink-300 focus:border-brand-500 focus:ring-brand-500"/>
                                @break
                            @case('fecha')
                                <input type="date" wire:model="valoresCamposCaso.{{ $campo->codigo }}"
                                       class="mt-1 block w-full text-sm rounded border-ink-300 focus:border-brand-500 focus:ring-brand-500"/>
                                @break
                            @case('fecha_hora')
                                <input type="datetime-local" wire:model="valoresCamposCaso.{{ $campo->codigo }}"
                                       class="mt-1 block w-full text-sm rounded border-ink-300 focus:border-brand-500 focus:ring-brand-500"/>
                                @break
                            @case('booleano')
                                <select wire:model="valoresCamposCaso.{{ $campo->codigo }}"
                                        class="mt-1 block w-full text-sm rounded border-ink-300 focus:border-brand-500 focus:ring-brand-500">
                                    <option value="">—</option>
                                    <option value="1">{{ __('casos.yes') }}</option>
                                    <option value="0">{{ __('casos.no') }}</option>
                                </select>
                                @break
                            @default
                                <input type="text" wire:model="valoresCamposCaso.{{ $campo->codigo }}"
                                       class="mt-1 block w-full text-sm rounded border-ink-300 focus:border-brand-500 focus:ring-brand-500"/>
                        @endswitch
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if($requiereCompromiso && $tipoCaso === 'cobranza')
        <div class="mt-3 rounded-md border border-warning-200 bg-warning-50 p-3">
            <div class="text-xs font-semibold uppercase tracking-wider text-warning-700">{{ __('casos.promise_title') }}</div>
            <div class="mt-2 grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-medium text-warning-700">
                        {{ __('casos.promise_amount') }} <span class="text-danger-600">*</span>
                    </label>
                    <input type="text" wire:model="promesaMonto" placeholder="0.00"
                           class="mt-1 block w-full text-sm rounded border-warning-300 focus:border-warning-500 focus:ring-amber-500"/>
                    @error('promesaMonto')<div class="text-xs text-danger-600 mt-0.5">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-warning-700">
                        {{ __('casos.promise_date') }} <span class="text-danger-600">*</span>
                    </label>
                    <input type="date" wire:model="promesaFecha"
                           class="mt-1 block w-full text-sm rounded border-warning-300 focus:border-warning-500 focus:ring-amber-500"/>
                    @error('promesaFecha')<div class="text-xs text-danger-600 mt-0.5">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-warning-700">{{ __('casos.promise_payment_type') }}</label>
                    <select wire:model="promesaTipoPagoId"
                            class="mt-1 block w-full text-sm rounded border-warning-300 focus:border-warning-500 focus:ring-amber-500">
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
        <div class="mt-3 rounded-md border border-success-200 bg-success-50 p-3">
            <div class="text-xs font-semibold uppercase tracking-wider text-success-800">{{ __('casos.close_title') }}</div>
            <div class="mt-2 grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-medium text-success-700">
                        {{ __('casos.close_amount') }} <span class="text-danger-600">*</span>
                    </label>
                    <input type="text" wire:model="cierreMonto" placeholder="0.00"
                           class="mt-1 block w-full text-sm rounded border-success-300 focus:border-success-500 focus:ring-emerald-500"/>
                    @error('cierreMonto')<div class="text-xs text-danger-600 mt-0.5">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-success-700">
                        {{ __('casos.close_estimated_date') }} <span class="text-danger-600">*</span>
                    </label>
                    <input type="date" wire:model="cierreFechaEstimada"
                           class="mt-1 block w-full text-sm rounded border-success-300 focus:border-success-500 focus:ring-emerald-500"/>
                    @error('cierreFechaEstimada')<div class="text-xs text-danger-600 mt-0.5">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-success-700">{{ __('casos.close_funnel_stage') }}</label>
                    <select wire:model="cierreEtapaEmbudoId"
                            class="mt-1 block w-full text-sm rounded border-success-300 focus:border-success-500 focus:ring-emerald-500">
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
        <div class="mt-3 rounded-md border border-brand-200 bg-brand-50 p-3">
            <div class="text-xs font-semibold uppercase tracking-wider text-brand-800">{{ __('casos.service_action_title') }}</div>
            <div class="mt-2 grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-brand-900">
                        {{ __('casos.service_action_desc') }} <span class="text-danger-600">*</span>
                    </label>
                    <input type="text" wire:model="accionDescripcion" maxlength="500"
                           placeholder="{{ __('casos.service_action_desc_ph') }}"
                           class="mt-1 block w-full text-sm rounded border-brand-300 focus:border-brand-500 focus:ring-brand-500"/>
                    @error('accionDescripcion')<div class="text-xs text-danger-600 mt-0.5">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-brand-900">
                        {{ __('casos.service_scheduled_date') }} <span class="text-danger-600">*</span>
                    </label>
                    <input type="datetime-local" wire:model="accionFechaProgramada"
                           class="mt-1 block w-full text-sm rounded border-brand-300 focus:border-brand-500 focus:ring-brand-500"/>
                    @error('accionFechaProgramada')<div class="text-xs text-danger-600 mt-0.5">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-brand-900">{{ __('casos.service_action_type') }}</label>
                    <select wire:model="accionTipoAccionId"
                            class="mt-1 block w-full text-sm rounded border-brand-300 focus:border-brand-500 focus:ring-brand-500">
                        <option value="">—</option>
                        @foreach($tiposAccionServicio as $ta)
                            <option value="{{ $ta->id }}">{{ $ta->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-brand-900">{{ __('casos.service_technician') }}</label>
                    <input type="text" wire:model="accionTecnicoAsignado" maxlength="150"
                           placeholder="{{ __('casos.service_technician_ph') }}"
                           class="mt-1 block w-full text-sm rounded border-brand-300 focus:border-brand-500 focus:ring-brand-500"/>
                </div>
            </div>
        </div>
    @endif

    @if($requiereCompromiso && $tipoCaso === 'ticket_cx')
        <div class="mt-3 rounded-md border border-sky-200 bg-sky-50 p-3">
            <div class="text-xs font-semibold uppercase tracking-wider text-sky-800">{{ __('casos.resolution_title') }}</div>
            <div class="mt-2 grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-sky-900">
                        {{ __('casos.resolution_action') }} <span class="text-danger-600">*</span>
                    </label>
                    <input type="text" wire:model="resolucionAccion" maxlength="500"
                           placeholder="{{ __('casos.resolution_action_ph') }}"
                           class="mt-1 block w-full text-sm rounded border-sky-300 focus:border-sky-500 focus:ring-sky-500"/>
                    @error('resolucionAccion')<div class="text-xs text-danger-600 mt-0.5">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-sky-900">
                        {{ __('casos.resolution_deadline') }} <span class="text-danger-600">*</span>
                    </label>
                    <input type="datetime-local" wire:model="resolucionFechaLimite"
                           class="mt-1 block w-full text-sm rounded border-sky-300 focus:border-sky-500 focus:ring-sky-500"/>
                    @error('resolucionFechaLimite')<div class="text-xs text-danger-600 mt-0.5">{{ $message }}</div>@enderror
                </div>
                <div class="sm:col-span-3 pt-2 mt-2 border-t border-sky-200">
                    <div class="text-[10px] font-semibold uppercase tracking-wider text-sky-700 mb-1">{{ __('casos.escalation_section') }}</div>
                    <label class="block text-xs font-medium text-sky-900">{{ __('casos.escalation_level') }}</label>
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
        <div class="text-[10px] text-ink-500">{{ __('casos.ctrl_enter_hint') }}</div>
        <button type="button" wire:click="guardar"
                class="inline-flex items-center px-4 py-2 bg-brand-600 text-white text-sm font-medium rounded-md hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500">
            {{ __('casos.submit_gestion') }}
        </button>
    </div>
</div>
