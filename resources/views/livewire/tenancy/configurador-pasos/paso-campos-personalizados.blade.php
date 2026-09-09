<div>
    @if(session('paso-campos-personalizados-ok'))<div class="alert alert-success" style="margin-bottom:14px;">{{ session('paso-campos-personalizados-ok') }}</div>@endif
    @if(session('paso-campos-personalizados-error'))<div class="alert alert-warning" style="margin-bottom:14px;">{{ session('paso-campos-personalizados-error') }}</div>@endif

    <div class="alert alert-info text-sm" style="margin-bottom:14px;">
        {{ __('configurador.campos.info_opcional') }}
    </div>

    {{-- Grupos: sólo un nombre y un orden. El acordeón de la Vista de Trabajo
         los pinta en este orden y mete dentro los campos de cada uno. --}}
    <div class="card" style="padding:12px 16px;margin-bottom:14px;">
        <div class="flex items-center flex-wrap gap-2.5">
            <strong class="text-sm">{{ __('configurador.campos.grupos_titulo') }}</strong>
            <span class="text-sm text-ink-500">{{ __('configurador.campos.grupos_ayuda') }}</span>
        </div>

        <div class="flex items-center flex-wrap gap-1.5" style="margin-top:10px;">
            @foreach($grupos as $i => $g)
                <span class="badge gap-1.5">
                    <button type="button" wire:click="moverGrupo({{ $g->id }}, -1)" class="btn btn-ghost btn-sm"
                            style="padding:0 2px;{{ $i === 0 ? 'visibility:hidden;' : '' }}" aria-label="{{ __('common.move_up') }}">↑</button>
                    {{ $g->nombre }}
                    <button type="button" wire:click="moverGrupo({{ $g->id }}, 1)" class="btn btn-ghost btn-sm"
                            style="padding:0 2px;{{ $i === $grupos->count() - 1 ? 'visibility:hidden;' : '' }}" aria-label="{{ __('common.move_down') }}">↓</button>
                    <button type="button" wire:click="eliminarGrupo({{ $g->id }})"
                            wire:confirm="{{ __('configurador.campos.grupos_confirm_eliminar') }}"
                            class="btn btn-ghost btn-sm" style="padding:0 2px;color:var(--danger-text);">×</button>
                </span>
            @endforeach

            <div class="flex items-center gap-1.5">
                <input type="text" wire:model="grupoNuevo" wire:keydown.enter="crearGrupo" class="input"
                       style="width:200px;height:28px;" placeholder="{{ __('configurador.campos.grupos_nuevo') }}"/>
                <button type="button" wire:click="crearGrupo" class="btn btn-ghost btn-sm">{{ __('common.add') }}</button>
            </div>
        </div>
        @error('grupoNuevo')<div class="field-error" style="margin-top:6px;">{{ $message }}</div>@enderror
    </div>

    <div class="card">
        <x-ui.toolbar :count="__('configurador.campos.n_campos', ['n' => $campos->count()])">
            <x-ui.search-input wire:model.live.debounce.300ms="busqueda" placeholder="{{ __('common.search') }}…" />
            <x-slot:acciones>
                <button type="button" wire:click="abrirFormCrear" class="btn btn-primary"><x-ui.icon name="plus" :size="14"/><span>{{ __('configurador.campos.nuevo') }}</span></button>
            </x-slot:acciones>
        </x-ui.toolbar>

        {{-- La tabla de antes sigue en pantalla mientras llega la nueva; sólo
             esta barra dice que se está trabajando. --}}
        <x-ui.cargando />

        @if($campos->isEmpty())
            <div class="empty">
                <div class="empty-icon"><x-ui.icon name="hash" :size="32"/></div>
                <div class="empty-title">{{ __('configurador.campos.sin_titulo') }}</div>
                <div class="empty-desc">{{ __('configurador.campos.sin_desc') }}</div>
            </div>
        @else
            <table class="table table-compact table-clickable">
                <thead>
                    <tr>
                        <th style="width:90px;">{{ __('configurador.campos.col_ambito') }}</th>
                        <th style="width:160px;">{{ __('configurador.campos.col_sub_ambito') }}</th>
                        <th style="width:160px;">{{ __('configurador.campo_codigo') }}</th>
                        <th>{{ __('configurador.campos.col_etiqueta') }}</th>
                        <th style="width:120px;">{{ __('configurador.campos.col_tipo') }}</th>
                        <th style="width:130px;">{{ __('configurador.campos.col_grupo') }}</th>
                        <th style="width:80px;">{{ __('configurador.campos.col_obligatorio') }}</th>
                        <th class="num" style="width:70px;">{{ __('configurador.campo_orden') }}</th>
                        <th style="width:110px;">{{ __('configurador.campo_estado') }}</th>
                        <th style="width:60px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($campos as $c)
                        <tr wire:key="paso-cp-{{ $c->id }}" wire:click="abrirFormEditar({{ $c->id }})">
                            <td><span class="text-xs text-ink-500 uppercase">{{ $c->ambito }}</span></td>
                            <td><span class="text-sm text-ink-600">{{ $c->cartera_nombre ?? $c->tipo_gestion_nombre ?? '—' }}</span></td>
                            <td><span class="font-mono text-sm">{{ $c->codigo }}</span></td>
                            <td><span class="font-medium">{{ $c->etiqueta }}</span></td>
                            <td><span class="text-xs text-ink-600">{{ str_replace('_', ' ', $c->tipo) }}</span></td>
                            <td>
                                @if($c->grupo_nombre)
                                    <span class="badge">{{ $c->grupo_nombre }}</span>
                                @else
                                    <span class="text-sm text-ink-400">—</span>
                                @endif
                                @unless($c->visible_en_gestion)
                                    <span class="badge badge-neutral" title="{{ __('configurador.campos.visible_en_gestion_ayuda') }}">{{ __('configurador.campos.oculto') }}</span>
                                @endunless
                            </td>
                            <td>
                                @if($c->obligatorio)
                                    <span class="badge badge-warning">{{ __('configurador.resultados.si') }}</span>
                                @else
                                    <span class="text-sm text-ink-400">—</span>
                                @endif
                            </td>
                            <td class="num">{{ $c->orden }}</td>
                            <td>
                                <span class="inline-flex items-center gap-1.5">
                                    <span class="dot dot-{{ $c->activo ? 'success' : 'neutral' }}"></span>
                                    {{ $c->activo ? __('configurador.activo') : __('configurador.inactivo') }}
                                </span>
                            </td>
                            <td class="text-ink-400"><x-ui.icon name="chevron-right" :size="14"/></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @if($formVisible)
        <div class="scrim" wire:click="cerrarForm" wire:key="paso-cp-scrim"></div>
        <div class="drawer" wire:key="paso-cp-drawer">
            <div class="drawer-header">
                <div class="text-md font-semibold">{{ $editandoId === null ? __('configurador.campos.drawer_nuevo') : __('configurador.campos.drawer_editar') }}</div>
                <button type="button" wire:click="cerrarForm" class="icon-btn"><x-ui.icon name="x" :size="14"/></button>
            </div>
            <div class="drawer-body">
                <div class="grid grid-cols-[1fr] gap-3.5">
                    <div>
                        <label class="field-label">{{ __('configurador.campos.campo_ambito') }}</label>
                        <select wire:model.live="form.ambito" class="select @error('form.ambito') input-error @enderror">
                            <option value="caso">{{ __('configurador.campos.ambito_caso') }}</option>
                            <option value="gestion">{{ __('configurador.campos.ambito_gestion') }}</option>
                        </select>
                        @error('form.ambito')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="field-label">{{ $form['ambito'] === 'gestion' ? __('configurador.campos.label_tipo_gestion') : __('configurador.campos.label_cartera') }}</label>
                        <select wire:model="form.ambito_id" class="select @error('form.ambito_id') input-error @enderror">
                            <option value="">{{ __('configurador.campos.seleccionar') }}</option>
                            @if($form['ambito'] === 'gestion')
                                @foreach($tiposGestion as $t)
                                    <option value="{{ $t->id }}">{{ $t->codigo }} — {{ $t->nombre }}</option>
                                @endforeach
                            @else
                                @foreach($carteras as $c)
                                    <option value="{{ $c->id }}">{{ $c->codigo }} — {{ $c->nombre }}</option>
                                @endforeach
                            @endif
                        </select>
                        @error('form.ambito_id')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="field-label">{{ __('configurador.campo_codigo') }}</label>
                        <input type="text" wire:model="form.codigo" maxlength="80" placeholder="dias_antiguedad"
                               class="input mono @error('form.codigo') input-error @enderror"/>
                        @error('form.codigo')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="field-label">{{ __('configurador.campos.campo_etiqueta') }}</label>
                        <input type="text" wire:model="form.etiqueta" maxlength="200"
                               class="input @error('form.etiqueta') input-error @enderror"/>
                        @error('form.etiqueta')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="field-label">{{ __('configurador.campo_descripcion') }}</label>
                        <textarea wire:model="form.descripcion" rows="2" maxlength="500" class="input"></textarea>
                    </div>
                    <div>
                        <label class="field-label">{{ __('configurador.campos.campo_tipo') }}</label>
                        <select wire:model="form.tipo" class="select @error('form.tipo') input-error @enderror">
                            @foreach($tiposCampo as $t)
                                <option value="{{ $t['valor'] }}">{{ $t['etiqueta'] }}</option>
                            @endforeach
                        </select>
                        @error('form.tipo')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="field-label">{{ __('configurador.campos.longitud_max') }}</label>
                        <input type="number" min="1" wire:model="form.longitud_max" class="input"/>
                    </div>
                    <div>
                        <label class="field-label">{{ __('configurador.campos.grupo') }}</label>
                        <select wire:model="form.grupo_campo_id" class="input">
                            <option value="">{{ __('configurador.campos.grupo_ninguno') }}</option>
                            @foreach($grupos as $g)
                                <option value="{{ $g->id }}">{{ $g->nombre }}</option>
                            @endforeach
                        </select>
                        @error('form.grupo_campo_id')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="grid grid-cols-[1fr_1fr] gap-3.5">
                        <div>
                            <label class="field-label">{{ __('configurador.campo_orden') }}</label>
                            <input type="number" min="0" wire:model="form.orden" class="input"/>
                        </div>
                        <div>
                            <label class="field-label">{{ __('configurador.campos.obligatorio') }}</label>
                            <label class="flex items-center gap-2" style="padding-top:8px;">
                                <input type="checkbox" wire:model="form.obligatorio"/>
                                <span class="text-base">{{ __('configurador.campos.obligatorio') }}</span>
                            </label>
                        </div>
                    </div>
                    <div>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" wire:model="form.activo"/>
                            <span class="text-base">{{ __('configurador.campos.campo_activo') }}</span>
                        </label>
                    </div>
                    <div>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" wire:model="form.visible_en_gestion"/>
                            <span class="text-base">{{ __('configurador.campos.visible_en_gestion') }}</span>
                        </label>
                        <div class="text-xs text-ink-500" style="margin-top:4px;">
                            {{ __('configurador.campos.visible_en_gestion_ayuda') }}
                        </div>
                    </div>
                </div>
            </div>
            <div class="drawer-footer">
                @if($editandoId !== null)
                    <button type="button" wire:click="eliminar({{ $editandoId }})" wire:confirm="{{ __('configurador.campos.confirm_eliminar') }}"
                            class="btn btn-ghost" style="color:var(--danger-text);margin-right:auto;">{{ __('common.delete') }}</button>
                @endif
                <button type="button" wire:click="cerrarForm" class="btn btn-ghost">{{ __('common.cancel') }}</button>
                <button type="button" wire:click="guardar" class="btn btn-primary">{{ __('common.save') }}</button>
            </div>
        </div>
    @endif
</div>
