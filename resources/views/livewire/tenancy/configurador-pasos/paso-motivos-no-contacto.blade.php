<div>
    @if(session('paso-motivos-no-contacto-ok'))
        <div class="alert alert-success" style="margin-bottom:14px;">{{ session('paso-motivos-no-contacto-ok') }}</div>
    @endif
    @if(session('paso-motivos-no-contacto-error'))
        <div class="alert alert-warning" style="margin-bottom:14px;">{{ session('paso-motivos-no-contacto-error') }}</div>
    @endif

    <div class="card">
        <x-ui.toolbar :count="__('configurador.motivos.n_motivos', ['n' => $motivos->count()])">
            <x-ui.search-input wire:model.live.debounce.300ms="busqueda"
                               placeholder="{{ __('common.search') }}…" />
            <x-slot:acciones>
                <button type="button" wire:click="abrirFormCrear" class="btn btn-primary">
                    <x-ui.icon name="plus" :size="14" />
                    <span>{{ __('configurador.motivos.nuevo') }}</span>
                </button>
            </x-slot:acciones>
        </x-ui.toolbar>

        {{-- La tabla de antes sigue en pantalla mientras llega la nueva; sólo
             esta barra dice que se está trabajando. --}}
        <x-ui.cargando />

        @if($motivos->isEmpty())
            <div class="empty">
                <div class="empty-icon"><x-ui.icon name="folder" :size="32" /></div>
                <div class="empty-title">{{ __('configurador.motivos.sin_titulo') }}</div>
                <div class="empty-desc">{{ __('configurador.motivos.sin_desc') }}</div>
            </div>
        @else
            <table class="table table-compact table-clickable">
                <thead>
                    <tr>
                        <th style="width:160px;">{{ __('configurador.campo_codigo') }}</th>
                        <th>{{ __('common.name') }}</th>
                        <th class="num" style="width:70px;">{{ __('configurador.campo_orden') }}</th>
                        <th style="width:110px;">{{ __('configurador.campo_estado') }}</th>
                        <th style="width:60px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($motivos as $m)
                        <tr wire:key="paso-motivo-{{ $m->id }}" wire:click="abrirFormEditar({{ $m->id }})">
                            <td><span class="font-mono text-sm">{{ $m->codigo }}</span></td>
                            <td><span class="font-medium">{{ $m->nombre }}</span></td>
                            <td class="num">{{ $m->orden }}</td>
                            <td>
                                <span style="display:inline-flex;align-items:center;gap:6px;">
                                    <span class="dot dot-{{ $m->activo ? 'success' : 'neutral' }}"></span>
                                    {{ $m->activo ? __('configurador.activo') : __('configurador.inactivo') }}
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
        <div class="scrim" wire:click="cerrarForm" wire:key="paso-motivo-scrim"></div>
        <div class="drawer" wire:key="paso-motivo-drawer">
            <div class="drawer-header">
                <div class="text-md font-semibold">
                    {{ $editandoId === null ? __('configurador.motivos.drawer_nuevo') : __('configurador.motivos.drawer_editar') }}
                </div>
                <button type="button" wire:click="cerrarForm" class="icon-btn" aria-label="{{ __('configurador.cerrar') }}">
                    <x-ui.icon name="x" :size="14" />
                </button>
            </div>
            <div class="drawer-body">
                <div style="display:grid;grid-template-columns:1fr;gap:14px;">
                    <div>
                        <label class="field-label">{{ __('configurador.campo_codigo') }}</label>
                        <input type="text" wire:model="form.codigo" placeholder="BUZON_VOZ" maxlength="50"
                               class="input mono uppercase @error('form.codigo') input-error @enderror"/>
                        @error('form.codigo')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="field-label">{{ __('common.name') }}</label>
                        <input type="text" wire:model="form.nombre" maxlength="150"
                               class="input @error('form.nombre') input-error @enderror"/>
                        @error('form.nombre')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
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
                            wire:confirm="{{ __('configurador.motivos.confirm_eliminar') }}"
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
    {{-- Causas de gestión. No tenían pantalla en ninguna parte: la tabla sólo se
         leía. En el proyecto de cobranza, cuatro de los nueve resultados exigen
         causa y la tabla estaba vacía, así que esas cuatro gestiones no se
         podían guardar. --}}
    <div class="card" style="padding:12px 16px;margin-top:14px;">
        <div class="flex items-center flex-wrap" style="gap:10px;">
            <strong class="text-base">{{ __('configurador.causas.titulo') }}</strong>
            <span class="text-sm text-ink-500">{{ __('configurador.causas.ayuda') }}</span>
        </div>

        <div class="flex items-center flex-wrap" style="gap:6px;margin-top:10px;">
            @foreach($causas as $c)
                <span class="badge" style="gap:6px;{{ $c->activo ? '' : 'opacity:.5;' }}">
                    <button type="button" wire:click="alternarCausa({{ $c->id }})" class="btn btn-ghost btn-sm" style="padding:0 2px;">
                        <span class="dot dot-{{ $c->activo ? 'success' : 'neutral' }}"></span>
                    </button>
                    {{ $c->nombre }}
                    <button type="button" wire:click="eliminarCausa({{ $c->id }})"
                            wire:confirm="{{ __('configurador.causas.confirm_eliminar') }}"
                            class="btn btn-ghost btn-sm" style="padding:0 2px;color:var(--danger-text);">×</button>
                </span>
            @endforeach

            <div class="flex items-center" style="gap:6px;">
                <input type="text" wire:model="causaNueva" wire:keydown.enter="crearCausa" class="input"
                       style="width:220px;height:28px;" placeholder="{{ __('configurador.causas.nueva') }}"/>
                <button type="button" wire:click="crearCausa" class="btn btn-ghost btn-sm">{{ __('common.add') }}</button>
            </div>
        </div>
        @error('causaNueva')<div class="field-error" style="margin-top:6px;">{{ $message }}</div>@enderror
    </div>
</div>
