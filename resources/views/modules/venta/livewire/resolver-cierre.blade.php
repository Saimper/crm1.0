<div>
    <div class="flex items-center gap-2">
        <button type="button" wire:click="abrir('ganado')"
                class="inline-flex items-center px-3 py-1.5 bg-success-600 text-white text-xs font-medium rounded hover:bg-success-700">
            Ganado
        </button>
        <button type="button" wire:click="abrir('perdido')"
                class="inline-flex items-center px-3 py-1.5 bg-danger-600 text-white text-xs font-medium rounded hover:bg-danger-700">
            Perdido
        </button>
        <button type="button" wire:click="abrir('cancelado')"
                class="inline-flex items-center px-3 py-1.5 bg-ink-500 text-white text-xs font-medium rounded hover:bg-ink-600">
            Cancelar
        </button>
    </div>

    @if(session('cierre-resuelto'))
        <div class="mt-2 text-xs text-success-700 bg-success-50 border border-success-200 rounded px-2 py-1"
             x-data="{show:true}" x-show="show" x-init="setTimeout(()=>show=false, 3000)">
            {{ session('cierre-resuelto') }}
        </div>
    @endif

    @if($modalAbierto)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-ink-900/40"
             wire:key="modal-resolver-venta-{{ $compromisoId }}">
            <div class="bg-white rounded-lg shadow-lg w-full max-w-sm p-5">
                <div class="text-sm font-semibold text-ink-900 mb-2">
                    Marcar cierre como <span class="capitalize">{{ $accion }}</span>
                </div>
                <label class="block text-xs font-medium text-ink-700">Fecha de resolución</label>
                <input type="date" wire:model="fechaResolucion"
                       class="mt-1 block w-full text-sm rounded border-ink-300 focus:border-brand-500 focus:ring-brand-500"/>
                @error('fechaResolucion')<div class="text-xs text-danger-600 mt-0.5">{{ $message }}</div>@enderror
                @error('accion')<div class="text-xs text-danger-600 mt-0.5">{{ $message }}</div>@enderror

                <div class="mt-4 flex items-center justify-end gap-2">
                    <button type="button" wire:click="cerrar"
                            class="px-3 py-1.5 text-xs text-ink-700 border border-ink-300 rounded hover:bg-ink-50">
                        Cancelar
                    </button>
                    <button type="button" wire:click="confirmar"
                            class="px-3 py-1.5 text-xs text-white bg-brand-600 rounded hover:bg-brand-700">
                        Confirmar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
