<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Nuevo caso</h1>
            <div class="page-subtitle">
                Tipo de proyecto: <strong>{{ ucfirst(str_replace('_', ' ', $tipoOperacion)) }}</strong>
                @if($persona)
                    · Persona:
                    <strong>
                        @if($persona->tipo_persona === 'juridica')
                            {{ $persona->razon_social }}
                        @else
                            {{ trim(($persona->nombres ?? '').' '.($persona->apellidos ?? '')) }}
                        @endif
                    </strong>
                    · <span class="font-mono">{{ $persona->identificacion }}</span>
                @endif
            </div>
        </div>
    </div>

    @if($persona === null)
        <div class="card card-pad">
            <div class="alert alert-warning">
                Selecciona una persona desde el listado para crear un caso. La pantalla
                espera <code>?persona={ulid}</code>.
            </div>
        </div>
    @else
        @error('general')<div class="alert alert-danger" style="margin-bottom:14px;">{{ $message }}</div>@enderror

        <div class="card card-pad" style="max-width:920px;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
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
                    <label class="field-label">Estado inicial</label>
                    <select wire:model="estadoCasoId" class="input @error('estadoCasoId') input-error @enderror">
                        <option value="">— Selecciona —</option>
                        @foreach($estados as $e)
                            <option value="{{ $e->id }}">{{ $e->nombre }}</option>
                        @endforeach
                    </select>
                    @error('estadoCasoId')<div class="field-error">{{ $message }}</div>@enderror
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

            @if($tipoOperacion === 'cobranza')
                <h3 style="font-size:13px;font-weight:600;margin-bottom:10px;">Datos del préstamo</h3>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;">
                    <div style="grid-column:span 2;">
                        <label class="field-label">Número de préstamo</label>
                        <input type="text" wire:model="numeroPrestamo" class="input mono uppercase @error('numeroPrestamo') input-error @enderror"/>
                        @error('numeroPrestamo')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="field-label">Moneda (3 letras)</label>
                        <input type="text" wire:model="moneda" class="input mono uppercase"/>
                    </div>
                    <div>
                        <label class="field-label">Monto original</label>
                        <input type="text" wire:model="montoOriginal" class="input mono"/>
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
                        <label class="field-label">Cuotas totales</label>
                        <input type="number" min="0" wire:model="cuotasTotales" class="input"/>
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
                        <label class="field-label">Fecha desembolso</label>
                        <input type="date" wire:model="fechaDesembolso" class="input @error('fechaDesembolso') input-error @enderror"/>
                        @error('fechaDesembolso')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="field-label">Fecha vencimiento</label>
                        <input type="date" wire:model="fechaVencimiento" class="input @error('fechaVencimiento') input-error @enderror"/>
                        @error('fechaVencimiento')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                </div>
            @elseif($tipoOperacion === 'cx')
                <h3 style="font-size:13px;font-weight:600;margin-bottom:10px;">Datos del ticket</h3>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                    <div>
                        <label class="field-label">Código ticket</label>
                        <input type="text" wire:model="codigoTicket" class="input mono uppercase @error('codigoTicket') input-error @enderror"/>
                        @error('codigoTicket')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="field-label">Asunto</label>
                        <input type="text" wire:model="asunto" class="input @error('asunto') input-error @enderror"/>
                        @error('asunto')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div style="grid-column:1 / -1;">
                        <label class="field-label">Descripción (opcional)</label>
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
                        <label class="field-label">Fecha reporte</label>
                        <input type="datetime-local" wire:model="fechaReporte" class="input @error('fechaReporte') input-error @enderror"/>
                        @error('fechaReporte')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                </div>
            @elseif($tipoOperacion === 'venta')
                <h3 style="font-size:13px;font-weight:600;margin-bottom:10px;">Datos del lead</h3>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                    <div>
                        <label class="field-label">Código lead</label>
                        <input type="text" wire:model="codigoLead" class="input mono uppercase @error('codigoLead') input-error @enderror"/>
                        @error('codigoLead')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="field-label">Valor estimado</label>
                        <input type="text" wire:model="valorEstimadoMonto" class="input mono"/>
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
                        <label class="field-label">Origen lead (opcional)</label>
                        <input type="text" wire:model="origenLead" class="input"/>
                    </div>
                    <div>
                        <label class="field-label">Primer contacto</label>
                        <input type="date" wire:model="fechaPrimerContacto" class="input @error('fechaPrimerContacto') input-error @enderror"/>
                        @error('fechaPrimerContacto')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                </div>
            @elseif($tipoOperacion === 'servicio')
                <h3 style="font-size:13px;font-weight:600;margin-bottom:10px;">Datos del servicio</h3>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                    <div>
                        <label class="field-label">Código servicio</label>
                        <input type="text" wire:model="codigoServicio" class="input mono uppercase @error('codigoServicio') input-error @enderror"/>
                        @error('codigoServicio')<div class="field-error">{{ $message }}</div>@enderror
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
                    <div>
                        <label class="field-label">Fecha solicitud</label>
                        <input type="date" wire:model="fechaSolicitud" class="input @error('fechaSolicitud') input-error @enderror"/>
                        @error('fechaSolicitud')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="field-label">Fecha programada (opcional)</label>
                        <input type="date" wire:model="fechaProgramada" class="input"/>
                    </div>
                    <div style="grid-column:1 / -1;">
                        <label class="field-label">Dirección (opcional)</label>
                        <input type="text" wire:model="direccionServicio" class="input"/>
                    </div>
                    <div>
                        <label class="field-label">Técnico asignado (opcional)</label>
                        <input type="text" wire:model="tecnicoAsignado" class="input"/>
                    </div>
                </div>
            @endif

            <div style="margin-top:20px;display:flex;justify-content:flex-end;gap:8px;">
                <a href="{{ route('proyectos.trabajo', ['proyecto_id' => app('tenancy.proyecto_activo')->id, 'persona' => $personaPublicId]) }}"
                   wire:navigate class="btn btn-ghost">Cancelar</a>
                <button type="button" wire:click="guardar" class="btn btn-primary">
                    Crear caso
                </button>
            </div>
        </div>
    @endif
</div>
