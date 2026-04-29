<div class="space-y-4">
    @if($mensajeExito)
        <div class="rounded-md bg-emerald-50 border border-emerald-200 px-4 py-2 text-sm text-emerald-800"
             x-data="{}" x-init="setTimeout(() => $wire.set('mensajeExito', null), 3000)">
            {{ $mensajeExito }}
        </div>
    @endif

    @error('general')
        <div class="rounded-md bg-red-50 border border-red-200 px-4 py-2 text-sm text-red-800">
            {{ $message }}
        </div>
    @enderror

    <form wire:submit.prevent="guardar" class="space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-medium uppercase tracking-wider text-gray-600 mb-1">Canal</label>
                <select wire:model="canalId" class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">— Selecciona —</option>
                    @foreach($canales as $c)
                        <option value="{{ $c->id }}">{{ $c->nombre }}</option>
                    @endforeach
                </select>
                @error('canalId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium uppercase tracking-wider text-gray-600 mb-1">Tipo de gestión</label>
                <select wire:model="tipoGestionId" class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">— Selecciona —</option>
                    @foreach($tipos as $t)
                        <option value="{{ $t->id }}">{{ $t->nombre }}</option>
                    @endforeach
                </select>
                @error('tipoGestionId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium uppercase tracking-wider text-gray-600 mb-1">Resultado</label>
                <select wire:model.live="resultadoId" class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">— Selecciona —</option>
                    @foreach($resultados as $r)
                        <option value="{{ $r->id }}">{{ $r->nombre }}</option>
                    @endforeach
                </select>
                @error('resultadoId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-medium uppercase tracking-wider text-gray-600 mb-1">Contacto usado</label>
                <select wire:model="contactoId" class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">— (ninguno) —</option>
                    @foreach($contactos as $c)
                        <option value="{{ $c->id }}">{{ ucfirst($c->tipo) }} · {{ $c->valor }}{{ $c->es_principal ? ' (principal)' : '' }}</option>
                    @endforeach
                </select>
            </div>

            @if($banderas['requiere_causa_mora'])
                <div>
                    <label class="block text-xs font-medium uppercase tracking-wider text-gray-600 mb-1">
                        Causa de mora <span class="text-red-600">*</span>
                    </label>
                    <select wire:model="causaMoraId" class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">— Selecciona —</option>
                        @foreach($causas as $c)
                            <option value="{{ $c->id }}">{{ $c->nombre }}</option>
                        @endforeach
                    </select>
                    @error('causaMoraId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            @endif

            @if($banderas['es_contacto_efectivo'] === false && $resultadoId !== null)
                <div>
                    <label class="block text-xs font-medium uppercase tracking-wider text-gray-600 mb-1">Motivo no contacto</label>
                    <select wire:model="motivoNoContactoId" class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">— (opcional) —</option>
                        @foreach($motivos as $m)
                            <option value="{{ $m->id }}">{{ $m->nombre }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
        </div>

        @if($banderas['requiere_promesa'])
            <div class="rounded-md bg-indigo-50 border border-indigo-200 p-4">
                <div class="text-xs font-semibold uppercase tracking-wider text-indigo-800 mb-2">Datos de la promesa</div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium uppercase tracking-wider text-gray-600 mb-1">Monto <span class="text-red-600">*</span></label>
                        <input type="number" step="0.01" min="0.01"
                               wire:model="montoPromesa"
                               class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                               placeholder="0.00">
                        @error('montoPromesa') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium uppercase tracking-wider text-gray-600 mb-1">Fecha de pago <span class="text-red-600">*</span></label>
                        <input type="date"
                               wire:model="fechaPromesa"
                               min="{{ now()->format('Y-m-d') }}"
                               class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('fechaPromesa') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
        @endif

        <div>
            <label class="block text-xs font-medium uppercase tracking-wider text-gray-600 mb-1">Notas (complemento)</label>
            <textarea wire:model="notas" rows="2"
                      class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                      placeholder="Detalle adicional opcional..."></textarea>
            @error('notas') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center justify-between">
            <div class="text-xs text-gray-500">
                @if($banderas['requiere_promesa'])
                    Este resultado <strong>exige</strong> datos de promesa.
                @elseif($banderas['requiere_causa_mora'])
                    Este resultado <strong>exige</strong> causa de mora.
                @endif
            </div>
            <button type="submit"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 disabled:opacity-50">
                <span wire:loading.remove>Registrar gestión</span>
                <span wire:loading>Guardando…</span>
            </button>
        </div>
    </form>
</div>
