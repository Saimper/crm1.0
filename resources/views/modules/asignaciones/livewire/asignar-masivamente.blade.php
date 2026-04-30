<div class="space-y-4">
    @if(session('asignacion-masiva-ok'))
        <div class="rounded border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
            {{ session('asignacion-masiva-ok') }}
        </div>
    @endif

    <section class="rounded-lg border border-gray-200 bg-white p-6 space-y-4">
        <div>
            <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-700">Asignar casos en batch a un equipo</h3>
            <p class="text-xs text-gray-500 mt-1">
                Los casos del proyecto sin asignación previa en la campaña se distribuyen <strong>round-robin</strong> entre los miembros activos del equipo.
                Si algún caso ya está asignado a la misma campaña, queda <em>omitido</em> automáticamente.
            </p>
        </div>

        <form wire:submit.prevent="asignar" class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
            <div>
                <label class="block text-xs font-medium text-gray-700">Campaña</label>
                <select wire:model.live="campanaId" class="mt-1 block w-full border-gray-300 rounded-md text-sm">
                    <option value="">Selecciona…</option>
                    @foreach($campanas as $c)
                        <option value="{{ $c->id }}">{{ $c->nombre }} ({{ $c->codigo }})</option>
                    @endforeach
                </select>
                @error('campanaId')<div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>@enderror
                @if($casosSinAsignar !== null)
                    <div class="mt-1 text-[11px] text-gray-500">
                        Casos sin asignar: <strong class="text-gray-800">{{ number_format($casosSinAsignar) }}</strong>
                    </div>
                @endif
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700">Equipo destino</label>
                <select wire:model.live="equipoId" class="mt-1 block w-full border-gray-300 rounded-md text-sm">
                    <option value="">Selecciona…</option>
                    @foreach($equipos as $e)
                        <option value="{{ $e->id }}">{{ $e->nombre }} ({{ $e->codigo }})</option>
                    @endforeach
                </select>
                @error('equipoId')<div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>@enderror
                @if($miembrosActivos !== null)
                    <div class="mt-1 text-[11px] text-gray-500">
                        Miembros activos: <strong class="text-gray-800">{{ $miembrosActivos }}</strong>
                    </div>
                @endif
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700">Límite (0 = todos)</label>
                <input type="number" wire:model="limite" min="0"
                       class="mt-1 block w-full border-gray-300 rounded-md text-sm"/>
                @error('limite')<div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>@enderror
            </div>
            <div class="md:col-span-3 flex justify-end">
                <button type="submit"
                        wire:confirm="¿Confirmar distribución round-robin?"
                        class="px-4 py-2 text-sm text-white bg-blue-600 rounded hover:bg-blue-700">
                    Asignar
                </button>
            </div>
        </form>
    </section>

    @if($ultAsignadas > 0 || $ultOmitidas > 0)
        <section class="rounded-lg border border-gray-200 bg-white p-4">
            <div class="flex items-center gap-4 text-sm mb-3">
                <span class="text-emerald-800">Asignadas: <strong>{{ $ultAsignadas }}</strong></span>
                <span class="text-amber-800">Omitidas: <strong>{{ $ultOmitidas }}</strong></span>
            </div>
            @if(! empty($ultDistribucion))
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-600">
                        <tr>
                            <th class="px-3 py-2 text-left">Usuario</th>
                            <th class="px-3 py-2 text-right">Casos recibidos</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($ultDistribucion as $uid => $cant)
                            <tr>
                                <td class="px-3 py-2">{{ $usuariosDistribucion[$uid] ?? 'Usuario #'.$uid }}</td>
                                <td class="px-3 py-2 text-right font-mono">{{ $cant }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    @endif
</div>
