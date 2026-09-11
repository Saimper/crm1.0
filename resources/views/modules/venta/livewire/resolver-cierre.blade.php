{{-- Botones de resolver un compromiso pendiente. Van en línea junto a
     «Editar»: cumplir en verde sólido, romper en rojo sin relleno, cancelar
     sin peso. El modal pide la fecha de resolución porque el dominio la exige. --}}
<div class="inline-flex flex-wrap items-center gap-1.5">
    <button type="button" wire:click="abrir('ganado')" class="btn btn-sm btn-success">{{ __('venta.btn_ganado') }}</button>
    <button type="button" wire:click="abrir('perdido')" class="btn btn-sm btn-secondary text-danger-600 border-danger-200 hover:bg-danger-50">{{ __('venta.btn_perdido') }}</button>
    <button type="button" wire:click="abrir('cancelado')" class="btn btn-sm btn-ghost text-ink-500">{{ __('venta.btn_cancelar') }}</button>

    @if(session('cierre-resuelto'))
        <span class="text-xs text-success-700" x-data="{show:true}" x-show="show" x-init="setTimeout(()=>show=false, 3000)">
            {{ session('cierre-resuelto') }}
        </span>
    @endif

    @if($modalAbierto)
        <div class="scrim" wire:click="cerrar" wire:key="scrim-resolver-venta-{{ $compromisoId }}"></div>
        <div class="modal max-w-[400px] p-5" role="dialog" aria-modal="true" wire:key="modal-resolver-venta-{{ $compromisoId }}">
            <div class="text-md font-semibold text-ink mb-3">
                {{ __('venta.modal_title') }} <span class="capitalize">{{ $accion }}</span>
            </div>
            <label class="field-label" for="fecha-resolver-venta-{{ $compromisoId }}">{{ __('venta.fecha_resolucion') }}</label>
            <input type="date" id="fecha-resolver-venta-{{ $compromisoId }}" wire:model="fechaResolucion" class="input font-mono"/>
            @error('fechaResolucion')<div class="field-error">{{ $message }}</div>@enderror
            @error('accion')<div class="field-error">{{ $message }}</div>@enderror

            <div class="mt-4 flex items-center justify-end gap-2">
                <button type="button" wire:click="cerrar" class="btn btn-secondary btn-sm">{{ __('common.cancel') }}</button>
                <button type="button" wire:click="confirmar" class="btn btn-primary btn-sm">{{ __('common.confirm') }}</button>
            </div>
        </div>
    @endif
</div>
