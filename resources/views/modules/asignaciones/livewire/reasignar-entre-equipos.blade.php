<div class="space-y-4">
    @if(session('reasignacion-ok'))
        <div class="rounded border border-success-200 bg-success-50 px-3 py-2 text-sm text-success-800">
            {{ session('reasignacion-ok') }}
        </div>
    @endif

    <section class="rounded-lg border border-ink-200 bg-white p-6 space-y-4">
        <div>
            <h3 class="text-sm font-semibold uppercase tracking-wider text-ink-700">Mover asignaciones pendientes entre equipos</h3>
            <p class="text-xs text-ink-500 mt-1">
                Solo se mueven asignaciones en estado <strong>pendiente</strong>. Las que ya están en trabajo o cerradas se respetan.
                Los casos se distribuyen <strong>round-robin</strong> entre los miembros activos del equipo destino.
            </p>
        </div>

        <form wire:submit.prevent="reasignar" class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
            <div>
                <label class="block text-xs font-medium text-ink-700">Equipo origen</label>
                <select wire:model.live="equipoOrigenId" class="mt-1 block w-full border-ink-300 rounded-md text-sm">
                    <option value="">Selecciona…</option>
                    @foreach($equipos as $e)
                        <option value="{{ $e->id }}">{{ $e->nombre }} ({{ $e->codigo }}){{ $e->activo ? '' : ' — inactivo' }}</option>
                    @endforeach
                </select>
                @error('equipoOrigenId')<div class="text-xs text-danger-600 mt-0.5">{{ $message }}</div>@enderror
                @if($pendientesOrigen !== null)
                    <div class="mt-1 text-[11px] text-ink-500">
                        Pendientes en origen: <strong class="text-ink-800">{{ number_format($pendientesOrigen) }}</strong>
                    </div>
                @endif
            </div>
            <div>
                <label class="block text-xs font-medium text-ink-700">Equipo destino</label>
                <select wire:model.live="equipoDestinoId" class="mt-1 block w-full border-ink-300 rounded-md text-sm">
                    <option value="">Selecciona…</option>
                    @foreach($equipos as $e)
                        @if($e->activo)
                            <option value="{{ $e->id }}">{{ $e->nombre }} ({{ $e->codigo }})</option>
                        @endif
                    @endforeach
                </select>
                @error('equipoDestinoId')<div class="text-xs text-danger-600 mt-0.5">{{ $message }}</div>@enderror
                @if($miembrosDestino !== null)
                    <div class="mt-1 text-[11px] text-ink-500">
                        Miembros activos: <strong class="text-ink-800">{{ $miembrosDestino }}</strong>
                    </div>
                @endif
            </div>
            <div>
                <label class="block text-xs font-medium text-ink-700">Límite (0 = todos)</label>
                <input type="number" wire:model="limite" min="0"
                       class="mt-1 block w-full border-ink-300 rounded-md text-sm"/>
                @error('limite')<div class="text-xs text-danger-600 mt-0.5">{{ $message }}</div>@enderror
            </div>
            <div class="md:col-span-3 flex justify-end">
                <button type="submit"
                        wire:confirm="¿Confirmar re-asignación? Se moverán las asignaciones pendientes entre equipos."
                        class="px-4 py-2 text-sm text-white bg-brand-600 rounded hover:bg-brand-700">
                    Re-asignar
                </button>
            </div>
        </form>
    </section>

    @if($ultMovidas > 0)
        <section class="rounded-lg border border-ink-200 bg-white p-4">
            <div class="flex items-center gap-4 text-sm mb-3">
                <span class="text-success-800">Movidas: <strong>{{ $ultMovidas }}</strong></span>
            </div>
            @if(! empty($ultDistribucion))
                <table class="min-w-full divide-y divide-ink-200 text-sm">
                    <thead class="bg-ink-50 text-xs uppercase tracking-wider text-ink-600">
                        <tr>
                            <th class="px-3 py-2 text-left">Nuevo gestor</th>
                            <th class="px-3 py-2 text-right">Casos recibidos</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100">
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
