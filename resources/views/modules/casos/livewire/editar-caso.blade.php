<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Editar caso</h1>
            <div class="page-subtitle">
                Tipo: <strong>{{ ucfirst(str_replace('_', ' ', $tipoCaso)) }}</strong>
                · Estado: <em>se modifica vía gestiones</em>
            </div>
        </div>
    </div>

    <div class="card card-pad" style="max-width:920px;">
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;">
            <div>
                <label class="field-label">Cartera</label>
                <select wire:model="carteraId" class="input @error('carteraId') input-error @enderror">
                    <option value="">— Selecciona —</option>
                    @foreach($carteras as $c)
                        <option value="{{ $c->id }}">{{ $c->nombre }}</option>
                    @endforeach
                </select>
                @error('carteraId')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div>
                <label class="field-label">Prioridad (0–9)</label>
                <input type="number" min="0" max="9" wire:model="prioridad" class="input"/>
            </div>
            <div>
                <label class="field-label">Fecha ingreso</label>
                <input type="date" wire:model="fechaIngreso" class="input @error('fechaIngreso') input-error @enderror"/>
                @error('fechaIngreso')<div class="field-error">{{ $message }}</div>@enderror
            </div>
        </div>

        <hr style="margin:20px 0;border:0;border-top:1px solid var(--border);">

        @if($tipoCaso === 'cobranza')
            <h3 style="font-size:13px;font-weight:600;margin-bottom:10px;">Datos del préstamo</h3>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;">
                <div>
                    <label class="field-label">Moneda</label>
                    <input type="text" wire:model="moneda" class="input mono uppercase"/>
                </div>
                <div>
                    <label class="field-label">Saldo capital</label>
                    <input type="text" wire:model="saldoCapital" class="input mono"/>
                </div>
                <div>
                    <label class="field-label">Saldo interés</label>
                    <input type="text" wire:model="saldoInteres" class="input mono"/>
                </div>
                <div>
                    <label class="field-label">Saldo total</label>
                    <input type="text" wire:model="saldoTotal" class="input mono"/>
                </div>
                <div>
                    <label class="field-label">Cuota mensual</label>
                    <input type="text" wire:model="cuotaMensual" class="input mono"/>
                </div>
                <div>
                    <label class="field-label">Cuotas pagadas</label>
                    <input type="number" min="0" wire:model="cuotasPagadas" class="input"/>
                </div>
                <div>
                    <label class="field-label">Días mora</label>
                    <input type="number" min="0" wire:model="diasMora" class="input"/>
                </div>
                <div>
                    <label class="field-label">Fecha vencimiento</label>
                    <input type="date" wire:model="fechaVencimiento" class="input @error('fechaVencimiento') input-error @enderror"/>
                    @error('fechaVencimiento')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            </div>
        @elseif($tipoCaso === 'ticket_cx')
            <h3 style="font-size:13px;font-weight:600;margin-bottom:10px;">Datos del ticket</h3>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div style="grid-column:1 / -1;">
                    <label class="field-label">Asunto</label>
                    <input type="text" wire:model="asunto" class="input @error('asunto') input-error @enderror"/>
                    @error('asunto')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div style="grid-column:1 / -1;">
                    <label class="field-label">Descripción</label>
                    <textarea wire:model="descripcion" rows="3" class="input"></textarea>
                </div>
                <div>
                    <label class="field-label">Categoría</label>
                    <select wire:model="categoriaTicketId" class="input">
                        <option value="">— Sin categoría —</option>
                        @foreach($catalogosTipo['categorias'] ?? [] as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="field-label">Prioridad ticket</label>
                    <select wire:model="prioridadTicketId" class="input">
                        <option value="">— Sin prioridad —</option>
                        @foreach($catalogosTipo['prioridades'] ?? [] as $pri)
                            <option value="{{ $pri->id }}">{{ $pri->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="field-label">Nivel SLA</label>
                    <select wire:model="nivelSlaId" class="input">
                        <option value="">— Sin SLA —</option>
                        @foreach($catalogosTipo['niveles_sla'] ?? [] as $sla)
                            <option value="{{ $sla->id }}">{{ $sla->nombre }}</option>
                        @endforeach
                    </select>
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
                <div>
                    <label class="field-label">Fecha límite SLA</label>
                    <input type="datetime-local" wire:model="fechaLimiteSla" class="input"/>
                </div>
            </div>
        @elseif($tipoCaso === 'lead_venta')
            <h3 style="font-size:13px;font-weight:600;margin-bottom:10px;">Datos del lead</h3>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div>
                    <label class="field-label">Valor estimado</label>
                    <input type="text" wire:model="valorEstimado" class="input mono"/>
                </div>
                <div>
                    <label class="field-label">Moneda</label>
                    <input type="text" wire:model="moneda" class="input mono uppercase"/>
                </div>
                <div>
                    <label class="field-label">Producto</label>
                    <select wire:model="productoVentaId" class="input">
                        <option value="">— Sin producto —</option>
                        @foreach($catalogosTipo['productos'] ?? [] as $prod)
                            <option value="{{ $prod->id }}">{{ $prod->nombre }}</option>
                        @endforeach
                    </select>
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
                <div>
                    <label class="field-label">Origen lead</label>
                    <input type="text" wire:model="origenLead" class="input"/>
                </div>
                <div>
                    <label class="field-label">Fecha estimada cierre</label>
                    <input type="date" wire:model="fechaEstimadaCierre" class="input"/>
                </div>
            </div>
        @elseif($tipoCaso === 'servicio')
            <h3 style="font-size:13px;font-weight:600;margin-bottom:10px;">Datos del servicio</h3>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div>
                    <label class="field-label">Tipo de acción</label>
                    <select wire:model="tipoAccionServicioId" class="input">
                        <option value="">— Sin tipo —</option>
                        @foreach($catalogosTipo['tipos_accion'] ?? [] as $ta)
                            <option value="{{ $ta->id }}">{{ $ta->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="field-label">Estado técnico</label>
                    <select wire:model="estadoTecnicoId" class="input">
                        <option value="">— Sin estado —</option>
                        @foreach($catalogosTipo['estados_tec'] ?? [] as $et)
                            <option value="{{ $et->id }}">{{ $et->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="grid-column:1 / -1;">
                    <label class="field-label">Dirección</label>
                    <input type="text" wire:model="direccionServicio" class="input"/>
                </div>
                <div>
                    <label class="field-label">Técnico asignado</label>
                    <input type="text" wire:model="tecnicoAsignado" class="input"/>
                </div>
                <div>
                    <label class="field-label">Fecha programada</label>
                    <input type="datetime-local" wire:model="fechaProgramada" class="input"/>
                </div>
            </div>
        @endif

        <div style="margin-top:20px;display:flex;justify-content:flex-end;gap:8px;">
            <a href="{{ route('proyectos.trabajo', ['proyecto_id' => app('tenancy.proyecto_activo')->id, 'persona' => $personaPublicId, 'caso' => $casoPublicId]) }}"
               wire:navigate class="btn btn-ghost">Cancelar</a>
            <button type="button" wire:click="guardar" class="btn btn-primary">
                Guardar cambios
            </button>
        </div>
    </div>
</div>
