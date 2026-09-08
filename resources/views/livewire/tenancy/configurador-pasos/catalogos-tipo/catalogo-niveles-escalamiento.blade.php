<div>
    @if(session('catalogo-niveles-escalamiento-ok'))<div class="alert alert-success" style="margin-bottom:14px;">{{ session('catalogo-niveles-escalamiento-ok') }}</div>@endif
    @if(session('catalogo-niveles-escalamiento-error'))<div class="alert alert-warning" style="margin-bottom:14px;">{{ session('catalogo-niveles-escalamiento-error') }}</div>@endif

    <div class="card">
        <x-ui.toolbar :count="__('configurador.niveles_escalamiento.n_niveles', ['n' => $rows->count()])">
            <x-ui.search-input wire:model.live.debounce.300ms="busqueda" placeholder="{{ __('common.search') }}…" />
            <x-slot:acciones>
                <button type="button" wire:click="abrirFormCrear" class="btn btn-primary"><x-ui.icon name="plus" :size="14"/><span>{{ __('configurador.niveles_escalamiento.nuevo') }}</span></button>
            </x-slot:acciones>
        </x-ui.toolbar>

        <x-ui.cargando />

        @if($rows->isEmpty())
            <div class="empty"><div class="empty-icon"><x-ui.icon name="folder" :size="32"/></div><div class="empty-title">{{ __('configurador.niveles_escalamiento.sin_titulo') }}</div></div>
        @else
            <table class="table table-compact table-clickable">
                <thead><tr><th style="width:160px;">{{ __('configurador.campo_codigo') }}</th><th>{{ __('common.name') }}</th><th class="num" style="width:80px;">{{ __('configurador.niveles_escalamiento.col_nivel') }}</th><th class="num" style="width:70px;">{{ __('configurador.campo_orden') }}</th><th style="width:110px;">{{ __('configurador.campo_estado') }}</th><th style="width:60px;"></th></tr></thead>
                <tbody>
                @foreach($rows as $r)
                    <tr wire:key="cat-ne-{{ $r->id }}" wire:click="abrirFormEditar({{ $r->id }})">
                        <td><span class="font-mono text-sm">{{ $r->codigo }}</span></td>
                        <td><span class="font-medium">{{ $r->nombre }}</span></td>
                        <td class="num">{{ $r->nivel }}</td>
                        <td class="num">{{ $r->orden }}</td>
                        <td><span class="inline-flex items-center gap-[6px]"><span class="dot dot-{{ $r->activo ? 'success' : 'neutral' }}"></span>{{ $r->activo ? __('configurador.activo') : __('configurador.inactivo') }}</span></td>
                        <td class="text-ink-400"><x-ui.icon name="chevron-right" :size="14"/></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @if($formVisible)
        <div class="scrim" wire:click="cerrarForm" wire:key="cat-ne-scrim"></div>
        <div class="drawer" wire:key="cat-ne-drawer">
            <div class="drawer-header"><div class="text-md font-semibold">{{ $editandoId === null ? __('configurador.niveles_escalamiento.drawer_nuevo') : __('configurador.niveles_escalamiento.drawer_editar') }}</div>
                <button type="button" wire:click="cerrarForm" class="icon-btn" aria-label="{{ __('configurador.cerrar') }}"><x-ui.icon name="x" :size="14"/></button></div>
            <div class="drawer-body"><div class="grid grid-cols-[1fr] gap-[14px]">
                <div><label class="field-label">{{ __('configurador.campo_codigo') }}</label><input type="text" wire:model="form.codigo" maxlength="50" class="input mono uppercase @error('form.codigo') input-error @enderror"/>@error('form.codigo')<div class="field-error">{{ $message }}</div>@enderror</div>
                <div><label class="field-label">{{ __('common.name') }}</label><input type="text" wire:model="form.nombre" maxlength="150" class="input @error('form.nombre') input-error @enderror"/>@error('form.nombre')<div class="field-error">{{ $message }}</div>@enderror</div>
                <div><label class="field-label">{{ __('configurador.niveles_escalamiento.campo_nivel') }}</label><input type="number" min="1" wire:model="form.nivel" class="input @error('form.nivel') input-error @enderror"/>@error('form.nivel')<div class="field-error">{{ $message }}</div>@enderror</div>
                <div class="grid grid-cols-[1fr_1fr] gap-[14px]">
                    <div><label class="field-label">{{ __('configurador.campo_orden') }}</label><input type="number" min="0" wire:model="form.orden" class="input"/></div>
                    <div><label class="field-label">{{ __('configurador.campo_estado') }}</label><label class="flex items-center gap-2" style="padding-top:8px;"><input type="checkbox" wire:model="form.activo"/><span class="text-base">{{ __('configurador.activo') }}</span></label></div>
                </div>
            </div></div>
            <div class="drawer-footer">
                @if($editandoId !== null)<button type="button" wire:click="eliminar({{ $editandoId }})" wire:confirm="{{ __('configurador.niveles_escalamiento.confirm_eliminar') }}" class="btn btn-ghost" style="color:var(--danger-text);margin-right:auto;">{{ __('common.delete') }}</button>@endif
                <button type="button" wire:click="cerrarForm" class="btn btn-ghost">{{ __('common.cancel') }}</button>
                <button type="button" wire:click="guardar" class="btn btn-primary">{{ __('common.save') }}</button>
            </div>
        </div>
    @endif
</div>
