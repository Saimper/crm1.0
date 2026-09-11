{{-- El atajo iba en `.window`, así que también disparaba mientras se escribía
     en el panel de entidades vinculadas, que es otro componente Livewire vivo
     en la misma pantalla. Acotado al formulario, y con Cmd para macOS.

     La raíz es la que hace scroll cuando la columna va pegada (≥1536px): así la
     barra de guardar queda siempre a la vista, con los campos condicionales
     desplegados o sin ellos. --}}
<div class="flex flex-col flex-1 min-h-0 2xl:overflow-y-auto"
     x-data
     @keydown.ctrl.enter="$wire.guardar()"
     @keydown.meta.enter="$wire.guardar()">

    <div class="px-[18px] py-4 flex flex-col gap-3.5">
        @if(session('nueva-gestion-ok'))
            <div class="alert alert-success text-sm" x-data="{show:true}" x-show="show" x-init="setTimeout(()=>show=false, 3000)">
                {{ session('nueva-gestion-ok') }}
            </div>
        @endif
        @error('general')<div class="alert alert-danger text-sm">{{ $message }}</div>@enderror

        {{-- Canal: chips, no desplegable. Son pocos y se eligen de un vistazo. --}}
        <div>
            <span class="field-label" id="gestion-channel-label">{{ __('casos.field_channel') }}</span>
            <div class="flex gap-1.5 flex-wrap" role="group" aria-labelledby="gestion-channel-label" id="gestion-channel">
                @foreach($canales as $c)
                    <button type="button" wire:click="elegirCanal({{ (int) $c->id }})" wire:key="canal-{{ $c->id }}"
                            class="chip {{ (int) $canalId === (int) $c->id ? 'active' : '' }}"
                            aria-pressed="{{ (int) $canalId === (int) $c->id ? 'true' : 'false' }}">{{ $c->nombre }}</button>
                @endforeach
            </div>
            @error('canalId')<div class="field-error">{{ $message }}</div>@enderror
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label for="gestion-type" class="field-label">{{ __('casos.field_gestion_type') }}</label>
                <select wire:model.live="tipoGestionId" id="gestion-type" @disabled($canalId === null)
                        class="select disabled:bg-surface-100 disabled:text-ink-400">
                    <option value="">{{ $canalId === null ? __('casos.pick_channel_first') : __('casos.pick_type') }}</option>
                    @foreach($tiposGestion as $t)
                        <option value="{{ $t->id }}">{{ $t->nombre }}</option>
                    @endforeach
                </select>
                @error('tipoGestionId')<div class="field-error">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="gestion-result" class="field-label">{{ __('casos.field_result') }}</label>
                {{-- Deshabilitado hasta que haya tipo: la lista depende de él. --}}
                <select wire:model.live="resultadoId" id="gestion-result" @disabled($tipoGestionId === null)
                        class="select disabled:bg-surface-100 disabled:text-ink-400">
                    <option value="">{{ $tipoGestionId === null ? __('casos.pick_type_first') : __('casos.pick_result') }}</option>
                    @foreach($resultados as $r)
                        <option value="{{ $r->id }}">{{ $r->nombre }}</option>
                    @endforeach
                </select>
                @error('resultadoId')<div class="field-error">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="gestion-contact" class="field-label">{{ __('casos.field_contact_used') }}</label>
                <select wire:model="contactoId" id="gestion-contact" class="select">
                    <option value="">—</option>
                    @foreach($contactos as $co)
                        <option value="{{ $co->id }}">{{ ucfirst($co->tipo) }} · {{ $co->valor }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="gestion-duration" class="field-label">{{ __('casos.field_duration') }}</label>
                <input type="number" min="0" step="1" wire:model="duracionSegundos" id="gestion-duration" class="input font-mono"/>
            </div>

            @if($esNoContactado && $resultadoId)
                <div class="col-span-full">
                    <label for="gestion-reason" class="field-label">{{ __('casos.field_no_contact_reason') }}</label>
                    <select wire:model="motivoNoContactoId" id="gestion-reason" class="select">
                        <option value="">—</option>
                        @foreach($motivos as $m)
                            <option value="{{ $m->id }}">{{ $m->nombre }}</option>
                        @endforeach
                    </select>
                    @error('motivoNoContactoId')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            @endif

            @if($requiereCausa)
                <div class="col-span-full">
                    <label for="gestion-cause" class="field-label">{{ __('casos.field_cause') }} <span class="text-danger-500">*</span></label>
                    <select wire:model="causaId" id="gestion-cause" class="select">
                        <option value="">—</option>
                        @foreach($causas as $ca)
                            <option value="{{ $ca->id }}">{{ $ca->nombre }}</option>
                        @endforeach
                    </select>
                    @error('causaId')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            @endif
        </div>

        {{-- Bloque de compromiso: sólo cuando el resultado lo exige. Se guarda en
             la misma transacción que la gestión. Un solo aspecto para los cuatro
             tipos: el color queda para los estados, no para el tipo de proyecto. --}}
        @if($requiereCompromiso && $tipoCaso === 'cobranza')
            <div class="border border-ink-200 rounded-lg px-3.5 pt-3 pb-3.5 bg-surface-50">
                <div class="flex items-center justify-between mb-2.5">
                    <span class="text-sm font-semibold text-ink">{{ __('casos.promise_title') }}</span>
                    <span class="text-xs text-ink-500">{{ __('casos.saved_with_gestion') }}</span>
                </div>
                <div class="grid grid-cols-2 gap-2.5">
                    <div>
                        <label class="field-label" for="promesa-monto">{{ __('casos.promise_amount') }} <span class="text-danger-500">*</span></label>
                        <div class="flex items-stretch h-9 border border-ink-200 rounded-md bg-white overflow-hidden focus-within:border-brand-500 focus-within:shadow-focus">
                            <span class="px-2.5 text-sm text-ink-500 border-r border-ink-200 flex items-center bg-surface-50">{{ moneda_local() }}</span>
                            <input type="text" id="promesa-monto" wire:model="promesaMonto" placeholder="{{ numero_local(0) }}"
                                   class="flex-1 min-w-0 h-full border-0 px-2.5 text-base font-mono focus:outline-none focus:ring-0"/>
                        </div>
                        @error('promesaMonto')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="field-label" for="promesa-fecha">{{ __('casos.promise_date') }} <span class="text-danger-500">*</span></label>
                        <input type="date" id="promesa-fecha" wire:model="promesaFecha" class="input font-mono"/>
                        @error('promesaFecha')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-span-full">
                        <label class="field-label" for="promesa-tipo-pago">{{ __('casos.promise_payment_type') }}</label>
                        <select id="promesa-tipo-pago" wire:model="promesaTipoPagoId" class="select">
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
            <div class="border border-ink-200 rounded-lg px-3.5 pt-3 pb-3.5 bg-surface-50">
                <div class="flex items-center justify-between mb-2.5">
                    <span class="text-sm font-semibold text-ink">{{ __('casos.close_title') }}</span>
                    <span class="text-xs text-ink-500">{{ __('casos.saved_with_gestion') }}</span>
                </div>
                <div class="grid grid-cols-2 gap-2.5">
                    <div>
                        <label class="field-label" for="cierre-monto">{{ __('casos.close_amount') }} <span class="text-danger-500">*</span></label>
                        <div class="flex items-stretch h-9 border border-ink-200 rounded-md bg-white overflow-hidden focus-within:border-brand-500 focus-within:shadow-focus">
                            <span class="px-2.5 text-sm text-ink-500 border-r border-ink-200 flex items-center bg-surface-50">{{ moneda_local() }}</span>
                            <input type="text" id="cierre-monto" wire:model="cierreMonto" placeholder="{{ numero_local(0) }}"
                                   class="flex-1 min-w-0 h-full border-0 px-2.5 text-base font-mono focus:outline-none focus:ring-0"/>
                        </div>
                        @error('cierreMonto')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="field-label" for="cierre-fecha">{{ __('casos.close_estimated_date') }} <span class="text-danger-500">*</span></label>
                        <input type="date" id="cierre-fecha" wire:model="cierreFechaEstimada" class="input font-mono"/>
                        @error('cierreFechaEstimada')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-span-full">
                        <label class="field-label" for="cierre-etapa">{{ __('casos.close_funnel_stage') }}</label>
                        <select id="cierre-etapa" wire:model="cierreEtapaEmbudoId" class="select">
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
            <div class="border border-ink-200 rounded-lg px-3.5 pt-3 pb-3.5 bg-surface-50">
                <div class="flex items-center justify-between mb-2.5">
                    <span class="text-sm font-semibold text-ink">{{ __('casos.service_action_title') }}</span>
                    <span class="text-xs text-ink-500">{{ __('casos.saved_with_gestion') }}</span>
                </div>
                <div class="grid grid-cols-2 gap-2.5">
                    <div class="col-span-full">
                        <label class="field-label" for="accion-descripcion">{{ __('casos.service_action_desc') }} <span class="text-danger-500">*</span></label>
                        <input type="text" id="accion-descripcion" wire:model="accionDescripcion" maxlength="500"
                               placeholder="{{ __('casos.service_action_desc_ph') }}" class="input"/>
                        @error('accionDescripcion')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="field-label" for="accion-fecha">{{ __('casos.service_scheduled_date') }} <span class="text-danger-500">*</span></label>
                        <input type="datetime-local" id="accion-fecha" wire:model="accionFechaProgramada" class="input font-mono"/>
                        @error('accionFechaProgramada')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="field-label" for="accion-tipo">{{ __('casos.service_action_type') }}</label>
                        <select id="accion-tipo" wire:model="accionTipoAccionId" class="select">
                            <option value="">—</option>
                            @foreach($tiposAccionServicio as $ta)
                                <option value="{{ $ta->id }}">{{ $ta->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-span-full">
                        <label class="field-label" for="accion-tecnico">{{ __('casos.service_technician') }}</label>
                        <input type="text" id="accion-tecnico" wire:model="accionTecnicoAsignado" maxlength="150"
                               placeholder="{{ __('casos.service_technician_ph') }}" class="input"/>
                    </div>
                </div>
            </div>
        @endif

        @if($requiereCompromiso && $tipoCaso === 'ticket_cx')
            <div class="border border-ink-200 rounded-lg px-3.5 pt-3 pb-3.5 bg-surface-50">
                <div class="flex items-center justify-between mb-2.5">
                    <span class="text-sm font-semibold text-ink">{{ __('casos.resolution_title') }}</span>
                    <span class="text-xs text-ink-500">{{ __('casos.saved_with_gestion') }}</span>
                </div>
                <div class="grid grid-cols-2 gap-2.5">
                    <div class="col-span-full">
                        <label class="field-label" for="resolucion-accion">{{ __('casos.resolution_action') }} <span class="text-danger-500">*</span></label>
                        <input type="text" id="resolucion-accion" wire:model="resolucionAccion" maxlength="500"
                               placeholder="{{ __('casos.resolution_action_ph') }}" class="input"/>
                        @error('resolucionAccion')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="field-label" for="resolucion-fecha">{{ __('casos.resolution_deadline') }} <span class="text-danger-500">*</span></label>
                        <input type="datetime-local" id="resolucion-fecha" wire:model="resolucionFechaLimite" class="input font-mono"/>
                        @error('resolucionFechaLimite')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="field-label" for="resolucion-nivel">{{ __('casos.escalation_section') }} · {{ __('casos.escalation_level') }}</label>
                        <select id="resolucion-nivel" wire:model="resolucionNivelEscalamientoId" class="select">
                            <option value="">—</option>
                            @foreach($nivelesEscalamiento as $ne)
                                <option value="{{ $ne->id }}">{{ $ne->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        @endif

        {{-- Campos personalizados ámbito gestion × tipo_gestion. Solo aparecen cuando
             el tipo seleccionado tiene definiciones; se persisten junto a la gestión. --}}
        @if($tipoGestionId && $camposGestion->isNotEmpty())
            <div class="pt-3 border-t border-ink-200">
                <h4 class="text-sm font-semibold text-ink mb-2">{{ __('casos.custom_fields_title') }}</h4>
                <div class="grid grid-cols-2 gap-3">
                    @foreach($camposGestion as $campo)
                        <div @class(['col-span-full' => in_array((string) $campo->tipo, ['texto_largo', 'seleccion_multiple'], true)])>
                            <label class="field-label">
                                {{ $campo->etiqueta }}
                                @if($campo->obligatorio)<span class="text-danger-500">*</span>@endif
                            </label>
                            <x-cp.control :campo="$campo" model="valoresCamposGestion.{{ $campo->codigo }}" clase="input" />
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Notas: crece con lo que se escribe hasta ocho líneas y cuenta lo que queda. --}}
        <div x-data="{
                notas: $wire.entangle('notas'),
                get restantes() { return 2000 - (this.notas ?? '').length },
                crecer(el) { el.style.height = 'auto'; el.style.height = Math.min(el.scrollHeight, 176) + 'px' },
                pegar(texto) {
                    this.notas = (this.notas ?? '').trim() === '' ? texto : (this.notas.trim() + ' ' + texto);
                    $nextTick(() => this.crecer($refs.notas));
                },
             }">
            <div class="flex items-baseline justify-between mb-1.5">
                <label for="gestion-notes" class="field-label mb-0">{{ __('casos.field_notes') }}</label>
                <span class="text-xs font-mono" :class="restantes < 0 ? 'text-danger-500' : 'text-ink-400'" x-text="restantes"></span>
            </div>

            @if($plantillasNota->isNotEmpty())
                <div class="flex gap-1.5 flex-wrap mb-2">
                    @foreach($plantillasNota as $plantilla)
                        <button type="button" x-on:click="pegar(@js($plantilla->texto))" class="chip h-6 px-[9px] text-xs">{{ $plantilla->etiqueta }}</button>
                    @endforeach
                </div>
            @endif

            <textarea wire:model="notas" id="gestion-notes" x-ref="notas" rows="3" maxlength="2000"
                      x-init="crecer($el)" x-on:input="crecer($el)"
                      class="textarea resize-none min-h-[72px]"
                      placeholder="{{ __('casos.notes_placeholder') }}"></textarea>
        </div>
    </div>

    {{-- Pegada abajo: con los campos condicionales desplegados el botón se iba
         fuera de pantalla y había que rebuscarlo. --}}
    <div class="sticky bottom-0 mt-auto px-[18px] py-3 border-t border-ink-200 bg-white/95 backdrop-blur rounded-b-lg flex items-center justify-between gap-3">
        <span class="text-xs text-ink-400">{{ __('casos.ctrl_enter_hint') }}</span>
        <button type="button" wire:click="guardar" wire:loading.attr="disabled" wire:target="guardar"
                class="btn btn-primary h-9 px-4 font-semibold disabled:opacity-70">
            <span wire:loading.remove wire:target="guardar">{{ __('casos.submit_gestion') }}</span>
            <span wire:loading wire:target="guardar">{{ __('common.saving') }}</span>
        </button>
    </div>
</div>
