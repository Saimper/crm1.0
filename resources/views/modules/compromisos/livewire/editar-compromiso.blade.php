<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Editar compromiso</h1>
            <div class="page-subtitle">
                Tipo: <strong>{{ str_replace('_', ' ', $tipoCompromiso) }}</strong>
                · Estado: <strong>{{ ucfirst($estado) }}</strong>
                · Solo editables mientras pendiente.
            </div>
        </div>
    </div>

    <div class="card card-pad" style="max-width:760px;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
            <div>
                <label class="field-label">Fecha vencimiento</label>
                <input type="date" wire:model="fechaVencimiento" class="input @error('fechaVencimiento') input-error @enderror"/>
                @error('fechaVencimiento')<div class="field-error">{{ $message }}</div>@enderror
            </div>

            @if($tipoCompromiso === 'promesa_pago')
                <div>
                    <label class="field-label">Monto</label>
                    <input type="text" wire:model="monto" class="input mono @error('monto') input-error @enderror"/>
                    @error('monto')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="field-label">Moneda</label>
                    <input type="text" wire:model="moneda" class="input mono uppercase"/>
                </div>
                <div>
                    <label class="field-label">Tipo de pago</label>
                    <select wire:model="tipoPagoId" class="input">
                        <option value="">— Sin tipo —</option>
                        @foreach($catalogosTipo['tipos_pago'] ?? [] as $tp)
                            <option value="{{ $tp->id }}">{{ $tp->nombre }}</option>
                        @endforeach
                    </select>
                </div>
            @elseif($tipoCompromiso === 'resolucion_ticket')
                <div style="grid-column:1 / -1;">
                    <label class="field-label">Acción comprometida</label>
                    <input type="text" wire:model="accionComprometida"
                           class="input @error('accionComprometida') input-error @enderror" maxlength="500"/>
                    @error('accionComprometida')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="field-label">Fecha límite SLA</label>
                    <input type="datetime-local" wire:model="fechaLimiteSla" class="input"/>
                </div>
                <div>
                    <label class="field-label">Nivel escalamiento</label>
                    <select wire:model="nivelEscalamientoId" class="input">
                        <option value="">— Sin escalamiento —</option>
                        @foreach($catalogosTipo['niveles_esc'] ?? [] as $ne)
                            <option value="{{ $ne->id }}">{{ $ne->nombre }}</option>
                        @endforeach
                    </select>
                </div>
            @elseif($tipoCompromiso === 'cierre_venta')
                <div>
                    <label class="field-label">Monto cierre</label>
                    <input type="text" wire:model="montoCierre" class="input mono @error('montoCierre') input-error @enderror"/>
                    @error('montoCierre')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="field-label">Moneda</label>
                    <input type="text" wire:model="moneda" class="input mono uppercase"/>
                </div>
                <div>
                    <label class="field-label">Etapa embudo</label>
                    <select wire:model="etapaEmbudoId" class="input">
                        <option value="">— Sin etapa —</option>
                        @foreach($catalogosTipo['etapas'] ?? [] as $et)
                            <option value="{{ $et->id }}">{{ $et->nombre }}</option>
                        @endforeach
                    </select>
                </div>
            @elseif($tipoCompromiso === 'accion_servicio')
                <div style="grid-column:1 / -1;">
                    <label class="field-label">Descripción acción</label>
                    <input type="text" wire:model="descripcionAccion"
                           class="input @error('descripcionAccion') input-error @enderror" maxlength="500"/>
                    @error('descripcionAccion')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="field-label">Fecha programada</label>
                    <input type="datetime-local" wire:model="fechaProgramada" class="input"/>
                </div>
                <div>
                    <label class="field-label">Tipo de acción</label>
                    <select wire:model="tipoAccionServicioId" class="input">
                        <option value="">— Sin tipo —</option>
                        @foreach($catalogosTipo['tipos_accion'] ?? [] as $ta)
                            <option value="{{ $ta->id }}">{{ $ta->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="grid-column:1 / -1;">
                    <label class="field-label">Técnico asignado (opcional)</label>
                    <input type="text" wire:model="tecnicoAsignado" class="input" maxlength="150"/>
                </div>
            @endif
        </div>

        <div style="margin-top:20px;display:flex;justify-content:flex-end;gap:8px;">
            <a href="{{ route('proyectos.trabajo', ['proyecto_id' => app('tenancy.proyecto_activo')->id, 'persona' => $personaPublicId, 'caso' => $casoPublicId]) }}"
               wire:navigate class="btn btn-ghost">Cancelar</a>
            <button type="button" wire:click="guardar" class="btn btn-primary">
                Guardar cambios
            </button>
        </div>
    </div>
</div>
