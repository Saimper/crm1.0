<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Entidades Configurables</h1>
            <div class="page-subtitle">Tablas tipadas definidas por administrador</div>
        </div>
        <div style="display:flex;gap:8px;">
            <a href="{{ route('admin.dashboard') }}" wire:navigate class="btn btn-ghost btn-sm">← Volver al panel</a>
            <button type="button" wire:click="abrirFormCrear" class="btn btn-primary">
                <x-ui.icon name="plus" :size="14" />
                Nueva entidad
            </button>
        </div>
    </div>

    @if(session('entidades-ok'))
        <div class="alert alert-success" style="margin-bottom:14px;">{{ session('entidades-ok') }}</div>
    @endif

    <div class="card card-pad" style="margin-bottom:14px;">
        <div style="display:flex;align-items:flex-end;gap:14px;">
            <div class="field" style="flex:1;max-width:360px;margin-bottom:0;">
                <label class="field-label">Proyecto</label>
                <select wire:model.live="proyectoSeleccionadoId" class="select">
                    @foreach($proyectos as $p)
                        <option value="{{ $p->id }}">{{ $p->codigo }} — {{ $p->nombre }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:260px 1fr;gap:14px;">
        {{-- Sidebar selector --}}
        <div class="card" style="padding:0;">
            <div style="padding:10px 12px;border-bottom:1px solid var(--border);font-size:12px;font-weight:500;color:var(--text-tertiary);text-transform:uppercase;letter-spacing:0.06em;">
                Entidades
            </div>
            @if($entidades->isEmpty())
                <div style="padding:16px;font-size:12px;color:var(--text-tertiary);">Sin entidades en este proyecto.</div>
            @else
                @foreach($entidades as $e)
                    <button type="button" wire:key="ent-{{ $e->id }}"
                            wire:click="abrirCamposDe({{ $e->id }})"
                            class="sb-item {{ $entidadConCamposAbiertosId === $e->id ? 'active' : '' }}"
                            style="height:auto;padding:10px 14px;text-align:left;">
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:13px;font-weight:500;">{{ $e->nombre }}</div>
                            <div style="font-size:11px;color:var(--text-tertiary);margin-top:2px;">
                                <span class="font-mono">{{ $e->codigo }}</span> · {{ $e->relacion_con }}
                                @if($e->cartera_nombre) · {{ $e->cartera_nombre }} @endif
                            </div>
                        </div>
                    </button>
                @endforeach
            @endif
        </div>

        {{-- Main detail --}}
        <div>
            @if($formVisible)
                <div class="card card-pad" style="margin-bottom:14px;border-color:var(--primary-soft-border);background:var(--primary-soft);">
                    <div style="font-size:13px;font-weight:600;color:var(--primary-text);margin-bottom:12px;">
                        {{ $entidadEditandoId === null ? 'Crear entidad' : 'Editar entidad' }}
                    </div>
                    <form wire:submit.prevent="guardarEntidad" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;">
                        <div class="field">
                            <label class="field-label">Código</label>
                            <input type="text" wire:model="formCodigo" placeholder="POLIZAS"
                                   class="input mono uppercase @error('formCodigo') input-error @enderror"/>
                            @error('formCodigo')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="field">
                            <label class="field-label">Nombre</label>
                            <input type="text" wire:model="formNombre" placeholder="Pólizas de seguro"
                                   class="input @error('formNombre') input-error @enderror"/>
                            @error('formNombre')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="field">
                            <label class="field-label">Ícono (opcional)</label>
                            <input type="text" wire:model="formIcono" class="input" placeholder="file-text"/>
                        </div>
                        <div class="field">
                            <label class="field-label">Relación con núcleo</label>
                            <select wire:model="formRelacion" class="select">
                                <option value="ninguna">Ninguna</option>
                                <option value="caso">Caso (1 caso → N)</option>
                                <option value="persona">Persona (1 persona → N)</option>
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label">Restringir a cartera</label>
                            <select wire:model="formCarteraId" class="select">
                                <option value="">Todas las carteras</option>
                                @foreach($carterasDelProyecto as $c)
                                    <option value="{{ $c->id }}">{{ $c->codigo }} — {{ $c->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label">Activa</label>
                            <select wire:model="formActivo" class="select">
                                <option value="1">Sí</option>
                                <option value="0">No</option>
                            </select>
                        </div>
                        <div class="field" style="grid-column:1 / -1;">
                            <label class="field-label">Descripción</label>
                            <textarea wire:model="formDescripcion" rows="2" class="textarea"></textarea>
                        </div>
                        <div style="grid-column:1 / -1;display:flex;justify-content:flex-end;gap:8px;">
                            <button type="button" wire:click="cerrarForm" class="btn btn-ghost">Cancelar</button>
                            <button type="submit" class="btn btn-primary">Guardar</button>
                        </div>
                    </form>
                </div>
            @endif

            @if($entidadConCamposAbiertosId !== null)
                @php
                    $entidadActiva = $entidades->firstWhere('id', $entidadConCamposAbiertosId);
                @endphp
                <div class="card" style="padding:16px;margin-bottom:14px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                        <div>
                            <div style="font-size:13px;font-weight:600;">Definición de campos</div>
                            @if($entidadActiva)
                                <div style="font-size:11px;color:var(--text-tertiary);margin-top:2px;">
                                    <span class="font-mono">{{ $entidadActiva->codigo }}</span> · {{ $entidadActiva->nombre }}
                                </div>
                            @endif
                        </div>
                        <div style="display:flex;gap:6px;">
                            @if(! $formCampoVisible)
                                <button type="button" wire:click="abrirFormCampoCrear" class="btn btn-secondary btn-sm">
                                    <x-ui.icon name="plus" :size="12" />
                                    Agregar campo
                                </button>
                            @endif
                            @if($entidadActiva)
                                <button type="button" wire:click="abrirFormEditar({{ $entidadActiva->id }})" class="btn btn-ghost btn-sm">
                                    <x-ui.icon name="edit" :size="12" />
                                    Editar entidad
                                </button>
                                <button type="button" wire:click="eliminarEntidad({{ $entidadActiva->id }})"
                                        wire:confirm="¿Desactivar la entidad?"
                                        class="btn btn-ghost btn-sm" style="color:var(--danger-text);">Desactivar</button>
                            @endif
                            <button type="button" wire:click="cerrarCampos" class="btn btn-ghost btn-sm">
                                <x-ui.icon name="x" :size="12" />
                            </button>
                        </div>
                    </div>

                    @if($formCampoVisible)
                        <form wire:submit.prevent="guardarCampo"
                              style="display:grid;grid-template-columns:repeat(5,1fr);gap:8px;background:var(--bg-subtle);padding:12px;border-radius:6px;border:1px solid var(--border);margin-bottom:14px;">
                            <div class="field" style="margin-bottom:0;">
                                <label class="field-label">Código</label>
                                <input type="text" wire:model="formCampoCodigo" placeholder="numero_poliza"
                                       class="input input-sm mono @error('formCampoCodigo') input-error @enderror"/>
                                @error('formCampoCodigo')<div class="field-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="field" style="margin-bottom:0;">
                                <label class="field-label">Etiqueta</label>
                                <input type="text" wire:model="formCampoEtiqueta"
                                       class="input input-sm @error('formCampoEtiqueta') input-error @enderror"/>
                                @error('formCampoEtiqueta')<div class="field-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="field" style="margin-bottom:0;">
                                <label class="field-label">Tipo</label>
                                <select wire:model="formCampoTipo" class="select input-sm">
                                    <option value="texto_corto">Texto corto</option>
                                    <option value="texto_largo">Texto largo</option>
                                    <option value="numero_entero">Entero</option>
                                    <option value="numero_decimal">Decimal</option>
                                    <option value="fecha">Fecha</option>
                                    <option value="fecha_hora">Fecha y hora</option>
                                    <option value="booleano">Sí/No</option>
                                    <option value="moneda">Moneda</option>
                                </select>
                            </div>
                            <div class="field" style="margin-bottom:0;">
                                <label class="field-label">Orden</label>
                                <input type="number" wire:model="formCampoOrden" min="0" class="input input-sm mono"/>
                            </div>
                            <div style="display:flex;align-items:flex-end;gap:6px;">
                                <label style="display:inline-flex;align-items:center;gap:6px;font-size:12px;">
                                    <input type="checkbox" wire:model="formCampoObligatorio" class="checkbox"/>
                                    Oblig.
                                </label>
                            </div>
                            <div style="grid-column:1 / -1;display:flex;justify-content:flex-end;gap:6px;">
                                <button type="button" wire:click="cerrarFormCampo" class="btn btn-ghost btn-sm">Cancelar</button>
                                <button type="submit" class="btn btn-primary btn-sm">Guardar campo</button>
                            </div>
                        </form>
                    @endif

                    @if($campos->isEmpty())
                        <div class="empty" style="padding:24px;">
                            <div class="empty-desc">Esta entidad aún no tiene campos.</div>
                        </div>
                    @else
                        <table class="table table-compact">
                            <thead>
                                <tr>
                                    <th style="width:160px;">Código</th>
                                    <th>Etiqueta</th>
                                    <th style="width:130px;">Tipo</th>
                                    <th style="width:90px;">Oblig.</th>
                                    <th class="num" style="width:80px;">Orden</th>
                                    <th style="width:110px;">Estado</th>
                                    <th style="width:80px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($campos as $c)
                                    <tr wire:key="campoent-{{ $c->id }}">
                                        <td><span class="font-mono" style="font-size:12px;">{{ $c->codigo }}</span></td>
                                        <td>{{ $c->etiqueta }}</td>
                                        <td><span style="color:var(--text-secondary);font-size:12px;">{{ $c->tipo }}</span></td>
                                        <td>
                                            @if($c->obligatorio)
                                                <x-ui.icon name="check" :size="14" style="color:var(--success-text);" />
                                            @else
                                                <span style="color:var(--text-muted);">—</span>
                                            @endif
                                        </td>
                                        <td class="num">{{ $c->orden }}</td>
                                        <td>
                                            <span style="display:inline-flex;align-items:center;gap:6px;">
                                                <span class="dot dot-{{ $c->activo ? 'success' : 'neutral' }}"></span>
                                                {{ $c->activo ? 'Activo' : 'Inactivo' }}
                                            </span>
                                        </td>
                                        <td>
                                            <div style="display:flex;gap:2px;">
                                                <button type="button" wire:click="abrirFormCampoEditar({{ $c->id }})" class="icon-btn" title="Editar">
                                                    <x-ui.icon name="edit" :size="12" />
                                                </button>
                                                @if($c->activo)
                                                    <button type="button" wire:click="desactivarCampo({{ $c->id }})"
                                                            class="icon-btn" style="color:var(--danger-text);" title="Desactivar">
                                                        <x-ui.icon name="trash" :size="12" />
                                                    </button>
                                                @else
                                                    <button type="button" wire:click="activarCampo({{ $c->id }})"
                                                            class="icon-btn" style="color:var(--success-text);" title="Activar">
                                                        <x-ui.icon name="check" :size="12" />
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            @else
                <div class="card card-pad">
                    <div class="empty" style="padding:24px;">
                        <div class="empty-icon"><x-ui.icon name="layers" :size="32" /></div>
                        <div class="empty-title">Selecciona una entidad</div>
                        <div class="empty-desc">Elige una entidad del panel izquierdo para ver y editar sus campos.</div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
