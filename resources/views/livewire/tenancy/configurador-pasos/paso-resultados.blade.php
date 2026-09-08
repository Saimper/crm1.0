<div>
    @if(session('paso-resultados-ok'))
        <div class="alert alert-success" style="margin-bottom:14px;">{{ session('paso-resultados-ok') }}</div>
    @endif
    @if(session('paso-resultados-error'))
        <div class="alert alert-warning" style="margin-bottom:14px;">{{ session('paso-resultados-error') }}</div>
    @endif

    <div class="card">
        <x-ui.toolbar :count="__('configurador.resultados.n_resultados', ['n' => $resultados->count()])">
            <x-ui.search-input wire:model.live.debounce.300ms="busqueda"
                               placeholder="{{ __('common.search') }}…" />
            <x-slot:acciones>
                <button type="button" wire:click="abrirFormCrear" class="btn btn-primary">
                    <x-ui.icon name="plus" :size="14" />
                    <span>{{ __('configurador.resultados.nuevo') }}</span>
                </button>
            </x-slot:acciones>
        </x-ui.toolbar>

        {{-- La tabla de antes sigue en pantalla mientras llega la nueva; sólo
             esta barra dice que se está trabajando. --}}
        <x-ui.cargando />

        @if($resultados->isEmpty())
            <div class="empty">
                <div class="empty-icon"><x-ui.icon name="folder" :size="32" /></div>
                <div class="empty-title">{{ __('configurador.resultados.sin_titulo') }}</div>
                <div class="empty-desc">{{ __('configurador.resultados.sin_desc') }}</div>
            </div>
        @else
            <table class="table table-compact table-clickable">
                <thead>
                    <tr>
                        <th style="width:160px;">{{ __('configurador.campo_codigo') }}</th>
                        <th>{{ __('common.name') }}</th>
                        <th style="width:90px;">{{ __('configurador.resultados.col_compromiso') }}</th>
                        <th style="width:70px;">{{ __('configurador.resultados.col_causa') }}</th>
                        <th style="width:90px;">{{ __('configurador.resultados.col_contacto_efectivo') }}</th>
                        <th class="num" style="width:70px;">{{ __('configurador.campo_orden') }}</th>
                        <th style="width:110px;">{{ __('configurador.campo_estado') }}</th>
                        <th style="width:60px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($resultados as $r)
                        <tr wire:key="paso-resultado-{{ $r->id }}" wire:click="abrirFormEditar({{ $r->id }})">
                            <td><span class="font-mono text-sm">{{ $r->codigo }}</span></td>
                            <td><span class="font-medium">{{ $r->nombre }}</span></td>
                            <td>
                                @if($r->requiere_compromiso)
                                    <span class="badge badge-warning">{{ __('configurador.resultados.si') }}</span>
                                @else
                                    <span class="text-sm text-ink-400">—</span>
                                @endif
                            </td>
                            <td>
                                @if($r->requiere_causa)
                                    <span class="badge badge-warning">{{ __('configurador.resultados.si') }}</span>
                                @else
                                    <span class="text-sm text-ink-400">—</span>
                                @endif
                            </td>
                            <td>
                                @if($r->es_contacto_efectivo)
                                    <span class="badge badge-success">{{ __('configurador.resultados.si') }}</span>
                                @else
                                    <span class="text-sm text-ink-400">—</span>
                                @endif
                            </td>
                            <td class="num">{{ $r->orden }}</td>
                            <td>
                                <span style="display:inline-flex;align-items:center;gap:6px;">
                                    <span class="dot dot-{{ $r->activo ? 'success' : 'neutral' }}"></span>
                                    {{ $r->activo ? __('configurador.activo') : __('configurador.inactivo') }}
                                </span>
                            </td>
                            <td class="text-ink-400"><x-ui.icon name="chevron-right" :size="14" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @if($formVisible)
        <div class="scrim" wire:click="cerrarForm" wire:key="paso-resultado-scrim"></div>
        <div class="drawer" wire:key="paso-resultado-drawer">
            <div class="drawer-header">
                <div class="text-md font-semibold">
                    {{ $editandoId === null ? __('configurador.resultados.drawer_nuevo') : __('configurador.resultados.drawer_editar') }}
                </div>
                <button type="button" wire:click="cerrarForm" class="icon-btn" aria-label="{{ __('configurador.cerrar') }}">
                    <x-ui.icon name="x" :size="14" />
                </button>
            </div>
            <div class="drawer-body">
                <div style="display:grid;grid-template-columns:1fr;gap:14px;">
                    <div>
                        <label class="field-label">{{ __('configurador.campo_codigo') }}</label>
                        <input type="text" wire:model="form.codigo" placeholder="CONTACTO_EFECTIVO" maxlength="50"
                               class="input mono uppercase @error('form.codigo') input-error @enderror"/>
                        @error('form.codigo')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="field-label">{{ __('common.name') }}</label>
                        <input type="text" wire:model="form.nombre" maxlength="150"
                               class="input @error('form.nombre') input-error @enderror"/>
                        @error('form.nombre')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="field-label">{{ __('configurador.campo_descripcion') }}</label>
                        <textarea wire:model="form.descripcion" rows="3" maxlength="500"
                                  class="input @error('form.descripcion') input-error @enderror"></textarea>
                        @error('form.descripcion')<div class="field-error">{{ $message }}</div>@enderror
                    </div>

                    <div class="flex flex-col gap-2" style="border-top:1px solid var(--border);padding-top:12px;">
                        <div class="label-xs" style="margin-bottom:4px;">{{ __('configurador.resultados.banderas') }}</div>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" wire:model="form.es_contacto_efectivo"/>
                            <span class="text-base text-ink-600">{{ __('configurador.resultados.es_contacto_efectivo') }}</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" wire:model="form.requiere_compromiso"/>
                            <span class="text-base text-ink-600">{{ __('configurador.resultados.requiere_compromiso') }}</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" wire:model="form.requiere_causa"/>
                            <span class="text-base text-ink-600">{{ __('configurador.resultados.requiere_causa') }}</span>
                        </label>
                    </div>

                    @if($estadosTerminales->isNotEmpty())
                        <div>
                            <label class="field-label">{{ __('configurador.resultados.estado_cierre') }}</label>
                            <select wire:model="form.estado_caso_cierre_id"
                                    class="input @error('form.estado_caso_cierre_id') input-error @enderror">
                                <option value="">{{ __('configurador.resultados.estado_cierre_ninguno') }}</option>
                                @foreach($estadosTerminales as $estado)
                                    <option value="{{ $estado->id }}">{{ $estado->nombre }}</option>
                                @endforeach
                            </select>
                            <div class="text-sm text-ink-600" style="margin-top:4px;">
                                {{ __('configurador.resultados.estado_cierre_ayuda') }}
                            </div>
                            @error('form.estado_caso_cierre_id')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                    @endif

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                        <div>
                            <label class="field-label">{{ __('configurador.campo_orden') }}</label>
                            <input type="number" min="0" wire:model="form.orden"
                                   class="input @error('form.orden') input-error @enderror"/>
                            @error('form.orden')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label class="field-label">{{ __('configurador.campo_estado') }}</label>
                            <label class="flex items-center gap-2" style="padding-top:8px;">
                                <input type="checkbox" wire:model="form.activo"/>
                                <span class="text-base text-ink-600">{{ __('configurador.activo') }}</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="drawer-footer">
                @if($editandoId !== null)
                    <button type="button"
                            wire:click="eliminar({{ $editandoId }})"
                            wire:confirm="{{ __('configurador.resultados.confirm_eliminar') }}"
                            class="btn btn-ghost"
                            style="color:var(--danger-text);margin-right:auto;">
                        {{ __('common.delete') }}
                    </button>
                @endif
                <button type="button" wire:click="cerrarForm" class="btn btn-ghost">{{ __('common.cancel') }}</button>
                <button type="button" wire:click="guardar" class="btn btn-primary">{{ __('common.save') }}</button>
            </div>
        </div>
    @endif
    {{-- Qué resultados admite cada tipo de gestión. Sin esto el selector de la
         Vista de Trabajo ofrecía los nueve resultados del proyecto sin importar
         el tipo, y «Promesa de pago fraccionado» aparecía bajo «No contactado».
         Un tipo sin ninguna casilla marcada admite todos: es el arranque, y es
         por tipo y no por proyecto para que nadie se quede sin poder gestionar
         el día que esto se despliegue. --}}
    @if($tiposGestion->isNotEmpty() && $resultados->isNotEmpty())
        <div class="card" style="margin-top:14px;">
            <div style="padding:12px 16px;border-bottom:1px solid var(--border);">
                <strong class="text-base">{{ __('configurador.matriz.titulo') }}</strong>
                <div class="text-sm text-ink-500" style="margin-top:2px;">{{ __('configurador.matriz.ayuda') }}</div>
            </div>
            <div class="scroll-x">
                <table class="table table-compact">
                    <thead>
                        <tr>
                            <th>{{ __('configurador.matriz.col_resultado') }}</th>
                            @foreach($tiposGestion as $tipo)
                                <th style="text-align:center;white-space:nowrap;">{{ $tipo->nombre }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($resultados as $resultado)
                            <tr wire:key="matriz-{{ $resultado->id }}">
                                <td>
                                    <span class="font-medium">{{ $resultado->nombre }}</span>
                                    @unless($resultado->activo)
                                        <span class="badge badge-neutral">{{ __('configurador.inactivo') }}</span>
                                    @endunless
                                </td>
                                @foreach($tiposGestion as $tipo)
                                    <td style="text-align:center;">
                                        <input type="checkbox"
                                               @checked($combinaciones->has($tipo->id.'-'.$resultado->id))
                                               wire:click="alternarCombinacion({{ $tipo->id }}, {{ $resultado->id }})"
                                               aria-label="{{ $tipo->nombre }} · {{ $resultado->nombre }}"/>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
    {{-- Frases hechas para el campo de notas. Con resultado, salen sólo bajo ese
         resultado; sin él, salen siempre. Es texto que se pega en el textarea y
         el gestor edita después: no ejecuta nada ni rellena otros campos. --}}
    <div class="card" style="padding:12px 16px;margin-top:14px;">
        <div class="flex items-center flex-wrap" style="gap:10px;">
            <strong class="text-base">{{ __('configurador.plantillas.titulo') }}</strong>
            <span class="text-sm text-ink-500">{{ __('configurador.plantillas.ayuda') }}</span>
        </div>

        @if($plantillas->isNotEmpty())
            <div class="flex flex-col gap-1" style="margin-top:10px;">
                @foreach($plantillas as $p)
                    <div class="flex items-center gap-2 text-sm">
                        <span class="badge">{{ $p->etiqueta }}</span>
                        <span class="text-ink-600 flex-1 min-w-0" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $p->texto }}</span>
                        @if($p->resultado_nombre)
                            <span class="badge badge-neutral">{{ $p->resultado_nombre }}</span>
                        @endif
                        <button type="button" wire:click="eliminarPlantilla({{ $p->id }})"
                                class="btn btn-ghost btn-sm" style="color:var(--danger-text);">×</button>
                    </div>
                @endforeach
            </div>
        @endif

        <div style="display:grid;grid-template-columns:170px 1fr 200px auto;gap:8px;align-items:end;margin-top:10px;">
            <div>
                <label class="field-label">{{ __('configurador.plantillas.etiqueta') }}</label>
                <input type="text" wire:model="plantilla.etiqueta" class="input" style="height:30px;"
                       placeholder="{{ __('configurador.plantillas.etiqueta_ph') }}"/>
            </div>
            <div>
                <label class="field-label">{{ __('configurador.plantillas.texto') }}</label>
                <input type="text" wire:model="plantilla.texto" class="input" style="height:30px;"
                       placeholder="{{ __('configurador.plantillas.texto_ph') }}"/>
            </div>
            <div>
                <label class="field-label">{{ __('configurador.plantillas.resultado') }}</label>
                <select wire:model="plantilla.resultado_id" class="input" style="height:30px;">
                    <option value="">{{ __('configurador.plantillas.siempre') }}</option>
                    @foreach($resultados as $r)
                        <option value="{{ $r->id }}">{{ $r->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <button type="button" wire:click="crearPlantilla" class="btn btn-ghost btn-sm">{{ __('common.add') }}</button>
        </div>
        @error('plantilla.etiqueta')<div class="field-error">{{ $message }}</div>@enderror
        @error('plantilla.texto')<div class="field-error">{{ $message }}</div>@enderror
    </div>
</div>
