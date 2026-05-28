<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">{{ __('compromisos.title_edit') }}</h1>
            <div class="page-subtitle">
                {{ __('compromisos.subtitle_edit_type', ['tipo' => str_replace('_', ' ', $tipoCompromiso)]) }}
                · {{ __('compromisos.subtitle_edit_state', ['estado' => ucfirst($estado)]) }}
                · {{ __('compromisos.subtitle_edit_pending') }}
            </div>
        </div>
    </div>

    <div class="card card-pad" style="max-width:760px;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
            <div>
                <label class="field-label">{{ __('compromisos.field_expiry_date') }}</label>
                <input type="date" wire:model="fechaVencimiento" class="input @error('fechaVencimiento') input-error @enderror"/>
                @error('fechaVencimiento')<div class="field-error">{{ $message }}</div>@enderror
            </div>

            @if($tipoCompromiso === 'promesa_pago')
                <div>
                    <label class="field-label">{{ __('compromisos.field_amount') }}</label>
                    <input type="text" wire:model="monto" class="input mono @error('monto') input-error @enderror"/>
                    @error('monto')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="field-label">{{ __('compromisos.field_currency') }}</label>
                    <input type="text" wire:model="moneda" class="input mono uppercase"/>
                </div>
                <div>
                    <label class="field-label">{{ __('compromisos.field_payment_type') }}</label>
                    <select wire:model="tipoPagoId" class="input">
                        <option value="">{{ __('compromisos.no_payment_type') }}</option>
                        @foreach($catalogosTipo['tipos_pago'] ?? [] as $tp)
                            <option value="{{ $tp->id }}">{{ $tp->nombre }}</option>
                        @endforeach
                    </select>
                </div>
            @elseif($tipoCompromiso === 'resolucion_ticket')
                <div style="grid-column:1 / -1;">
                    <label class="field-label">{{ __('compromisos.field_committed_action') }}</label>
                    <input type="text" wire:model="accionComprometida"
                           class="input @error('accionComprometida') input-error @enderror" maxlength="500"/>
                    @error('accionComprometida')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="field-label">{{ __('compromisos.field_sla_deadline') }}</label>
                    <input type="datetime-local" wire:model="fechaLimiteSla" class="input"/>
                </div>
                <div>
                    <label class="field-label">{{ __('compromisos.field_escalation_level') }}</label>
                    <select wire:model="nivelEscalamientoId" class="input">
                        <option value="">{{ __('compromisos.no_escalation') }}</option>
                        @foreach($catalogosTipo['niveles_esc'] ?? [] as $ne)
                            <option value="{{ $ne->id }}">{{ $ne->nombre }}</option>
                        @endforeach
                    </select>
                </div>
            @elseif($tipoCompromiso === 'cierre_venta')
                <div>
                    <label class="field-label">{{ __('compromisos.field_close_amount') }}</label>
                    <input type="text" wire:model="montoCierre" class="input mono @error('montoCierre') input-error @enderror"/>
                    @error('montoCierre')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="field-label">{{ __('compromisos.field_currency') }}</label>
                    <input type="text" wire:model="moneda" class="input mono uppercase"/>
                </div>
                <div>
                    <label class="field-label">{{ __('compromisos.field_funnel_stage') }}</label>
                    <select wire:model="etapaEmbudoId" class="input">
                        <option value="">{{ __('compromisos.no_stage') }}</option>
                        @foreach($catalogosTipo['etapas'] ?? [] as $et)
                            <option value="{{ $et->id }}">{{ $et->nombre }}</option>
                        @endforeach
                    </select>
                </div>
            @elseif($tipoCompromiso === 'accion_servicio')
                <div style="grid-column:1 / -1;">
                    <label class="field-label">{{ __('compromisos.field_action_desc') }}</label>
                    <input type="text" wire:model="descripcionAccion"
                           class="input @error('descripcionAccion') input-error @enderror" maxlength="500"/>
                    @error('descripcionAccion')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="field-label">{{ __('compromisos.field_scheduled_date') }}</label>
                    <input type="datetime-local" wire:model="fechaProgramada" class="input"/>
                </div>
                <div>
                    <label class="field-label">{{ __('compromisos.field_action_type') }}</label>
                    <select wire:model="tipoAccionServicioId" class="input">
                        <option value="">{{ __('compromisos.no_action_type') }}</option>
                        @foreach($catalogosTipo['tipos_accion'] ?? [] as $ta)
                            <option value="{{ $ta->id }}">{{ $ta->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="grid-column:1 / -1;">
                    <label class="field-label">{{ __('compromisos.field_technician') }}</label>
                    <input type="text" wire:model="tecnicoAsignado" class="input" maxlength="150"/>
                </div>
            @endif
        </div>

        <div style="margin-top:20px;display:flex;justify-content:flex-end;gap:8px;">
            <a href="{{ route('proyectos.trabajo', ['proyecto_id' => app('tenancy.proyecto_activo')->id, 'persona' => $personaPublicId, 'caso' => $casoPublicId]) }}"
               wire:navigate class="btn btn-ghost">{{ __('common.cancel') }}</a>
            <button type="button" wire:click="guardar" class="btn btn-primary">
                {{ __('compromisos.save_changes') }}
            </button>
        </div>
    </div>
</div>
