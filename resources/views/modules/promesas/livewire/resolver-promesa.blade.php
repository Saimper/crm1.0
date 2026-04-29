<div>
    <div class="flex items-center gap-2">
        <button type="button"
                wire:click="abrir('cumplida')"
                class="inline-flex items-center px-3 py-1.5 bg-emerald-600 text-white text-xs font-medium rounded-md hover:bg-emerald-700">
            Cumplida
        </button>
        <button type="button"
                wire:click="abrir('rota')"
                class="inline-flex items-center px-3 py-1.5 bg-red-600 text-white text-xs font-medium rounded-md hover:bg-red-700">
            Rota
        </button>
        <button type="button"
                wire:click="abrir('cancelada')"
                class="inline-flex items-center px-3 py-1.5 bg-gray-600 text-white text-xs font-medium rounded-md hover:bg-gray-700">
            Cancelar
        </button>
    </div>

    @if($modalAbierto)
        @php
            $titulos = [
                'cumplida'  => ['Marcar promesa cumplida', 'emerald', 'Confirma que el deudor pagó según lo prometido.'],
                'rota'      => ['Marcar promesa rota',     'red',     'Confirma que la fecha pactada pasó sin pago.'],
                'cancelada' => ['Cancelar promesa',        'gray',    'La promesa queda sin efecto (p.ej. renegociación).'],
            ];
            [$titulo, $color, $texto] = $titulos[$accion] ?? ['', 'gray', ''];
        @endphp

        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
             wire:click.self="cerrar"
             x-data="{}"
             @keydown.escape.window="$wire.cerrar()">
            <div class="w-full max-w-md rounded-lg bg-white shadow-2xl">
                <div class="border-b border-gray-200 px-6 py-3">
                    <h3 class="text-base font-semibold text-gray-900">{{ $titulo }}</h3>
                </div>

                <form wire:submit.prevent="confirmar">
                    <div class="px-6 py-4 space-y-4">
                        <p class="text-sm text-gray-700">{{ $texto }}</p>

                        <div class="rounded border border-gray-200 bg-gray-50 p-3 text-xs text-gray-700 space-y-0.5">
                            <div><span class="text-gray-500">Monto prometido:</span> {{ $moneda }} {{ number_format((float) $monto, 2) }}</div>
                            <div><span class="text-gray-500">Fecha de pago:</span> {{ \Illuminate\Support\Carbon::parse($fechaPromesa)->format('d M Y') }}</div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium uppercase tracking-wider text-gray-600 mb-1">
                                Fecha de resolución
                            </label>
                            <input type="date"
                                   wire:model="fechaResolucion"
                                   max="{{ now()->format('Y-m-d') }}"
                                   class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('fechaResolucion') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        @error('general')
                            <div class="rounded-md bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-800">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    <div class="border-t border-gray-200 bg-gray-50 px-6 py-3 flex items-center justify-end gap-2">
                        <button type="button"
                                wire:click="cerrar"
                                class="px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-200 rounded-md">
                            Cancelar
                        </button>
                        <button type="submit"
                                wire:loading.attr="disabled"
                                @class([
                                    'inline-flex items-center px-4 py-1.5 text-white text-sm font-medium rounded-md disabled:opacity-50',
                                    'bg-emerald-600 hover:bg-emerald-700' => $color === 'emerald',
                                    'bg-red-600 hover:bg-red-700'         => $color === 'red',
                                    'bg-gray-600 hover:bg-gray-700'       => $color === 'gray',
                                ])>
                            <span wire:loading.remove>Confirmar</span>
                            <span wire:loading>Guardando…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
