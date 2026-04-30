<div>
    <div class="flex items-center gap-2">
        <button type="button" wire:click="abrir('cumplida')"
                class="inline-flex items-center px-3 py-1.5 bg-emerald-600 text-white text-xs font-medium rounded hover:bg-emerald-700">
            Cumplida
        </button>
        <button type="button" wire:click="abrir('rota')"
                class="inline-flex items-center px-3 py-1.5 bg-red-600 text-white text-xs font-medium rounded hover:bg-red-700">
            Rota
        </button>
        <button type="button" wire:click="abrir('cancelada')"
                class="inline-flex items-center px-3 py-1.5 bg-gray-500 text-white text-xs font-medium rounded hover:bg-gray-600">
            Cancelar
        </button>
    </div>

    @if(session('promesa-resuelta'))
        <div class="mt-2 text-xs text-emerald-700 bg-emerald-50 border border-emerald-200 rounded px-2 py-1"
             x-data="{show:true}" x-show="show" x-init="setTimeout(()=>show=false, 3000)">
            {{ session('promesa-resuelta') }}
        </div>
    @endif

    @if($modalAbierto)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/40"
             wire:key="modal-resolver-{{ $compromisoId }}">
            <div class="bg-white rounded-lg shadow-lg w-full max-w-sm p-5">
                <div class="text-sm font-semibold text-gray-900 mb-2">
                    Marcar promesa como <span class="capitalize">{{ $accion }}</span>
                </div>
                <label class="block text-xs font-medium text-gray-700">Fecha de resolución</label>
                <input type="date" wire:model="fechaResolucion"
                       class="mt-1 block w-full text-sm rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500"/>
                @error('fechaResolucion')<div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>@enderror
                @error('accion')<div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>@enderror

                <div class="mt-4 flex items-center justify-end gap-2">
                    <button type="button" wire:click="cerrar"
                            class="px-3 py-1.5 text-xs text-gray-700 border border-gray-300 rounded hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button type="button" wire:click="confirmar"
                            class="px-3 py-1.5 text-xs text-white bg-blue-600 rounded hover:bg-blue-700">
                        Confirmar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
