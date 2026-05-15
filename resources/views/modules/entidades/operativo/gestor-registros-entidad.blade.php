<div class="space-y-4">
    @if(session('entidades-registros-ok'))
        <div class="rounded border border-success-200 bg-success-50 px-3 py-2 text-sm text-success-800">
            {{ session('entidades-registros-ok') }}
        </div>
    @endif

    @if($entidad === null)
        <div class="p-6 text-sm text-ink-500 text-center">Entidad no encontrada.</div>
    @else
        <section class="rounded-lg border border-ink-200 bg-white p-4 flex items-center justify-between">
            <div>
                <h3 class="text-sm font-semibold uppercase tracking-wider text-ink-700">{{ $entidad->nombre }}</h3>
                <p class="text-xs text-ink-500 mt-1">{{ $entidad->descripcion ?? '' }}</p>
            </div>
            @if(auth()->user()->tienePermiso('entidades.crear', $proyectoId) && ! $formVisible)
                <button type="button" wire:click="abrirFormCrear"
                        class="px-3 py-1.5 text-xs text-white bg-brand-600 rounded hover:bg-brand-700">
                    Nuevo registro
                </button>
            @endif
        </section>

        @if($formVisible)
            <section class="rounded-lg border border-brand-200 bg-brand-50 p-4">
                <h4 class="text-sm font-semibold text-brand-900 mb-3">
                    {{ $registroEditandoId === null ? 'Crear registro' : 'Editar registro' }}
                </h4>
                <form wire:submit.prevent="guardar" class="space-y-3 text-sm">
                    <div>
                        <label class="block text-xs font-medium text-ink-700">Título</label>
                        <input type="text" wire:model="titulo"
                               class="mt-1 block w-full border-ink-300 rounded-md text-sm"
                               placeholder="Identificador visible"/>
                        @error('titulo')<div class="text-xs text-danger-600 mt-0.5">{{ $message }}</div>@enderror
                    </div>

                    @foreach($campos as $campo)
                        @php $codigo = (string) $campo->codigo; @endphp
                        <div>
                            <label class="block text-xs font-medium text-ink-700">
                                {{ $campo->etiqueta }}
                                @if($campo->obligatorio)<span class="text-danger-500">*</span>@endif
                            </label>
                            @switch($campo->tipo)
                                @case('texto_largo')
                                    <textarea wire:model="valores.{{ $codigo }}" rows="3"
                                              class="mt-1 block w-full border-ink-300 rounded-md text-sm"></textarea>
                                    @break
                                @case('numero_entero')
                                @case('numero_decimal')
                                @case('moneda')
                                    <input type="number" step="{{ $campo->tipo === 'numero_entero' ? '1' : '0.01' }}"
                                           wire:model="valores.{{ $codigo }}"
                                           class="mt-1 block w-full border-ink-300 rounded-md text-sm"/>
                                    @break
                                @case('fecha')
                                    <input type="date" wire:model="valores.{{ $codigo }}"
                                           class="mt-1 block w-full border-ink-300 rounded-md text-sm"/>
                                    @break
                                @case('fecha_hora')
                                    <input type="datetime-local" wire:model="valores.{{ $codigo }}"
                                           class="mt-1 block w-full border-ink-300 rounded-md text-sm"/>
                                    @break
                                @case('booleano')
                                    <label class="mt-1 flex items-center gap-2 text-sm">
                                        <input type="checkbox" wire:model="valores.{{ $codigo }}"
                                               class="rounded border-ink-300"/>
                                        <span>Sí</span>
                                    </label>
                                    @break
                                @default
                                    <input type="text" wire:model="valores.{{ $codigo }}"
                                           class="mt-1 block w-full border-ink-300 rounded-md text-sm"/>
                            @endswitch
                        </div>
                    @endforeach

                    <div class="flex items-center justify-end gap-2">
                        <button type="button" wire:click="cerrarForm"
                                class="px-3 py-1.5 text-xs text-ink-700 border border-ink-300 rounded hover:bg-ink-50">Cancelar</button>
                        <button type="submit"
                                class="px-3 py-1.5 text-xs text-white bg-brand-600 rounded hover:bg-brand-700">Guardar</button>
                    </div>
                </form>
            </section>
        @endif

        <section class="rounded-lg border border-ink-200 bg-white overflow-hidden">
            <div class="px-4 py-3 border-b border-ink-200 bg-ink-50 text-xs font-semibold uppercase tracking-wider text-ink-600">
                Registros ({{ $registros->count() }})
            </div>
            @if($registros->isEmpty())
                <div class="p-6 text-sm text-ink-500 text-center">Aún no hay registros para esta entidad.</div>
            @else
                <table class="min-w-full divide-y divide-ink-200 text-sm">
                    <thead class="bg-ink-50 text-xs uppercase tracking-wider text-ink-600">
                        <tr>
                            <th class="px-3 py-2 text-left">Título</th>
                            <th class="px-3 py-2 text-left">Creado</th>
                            <th class="px-3 py-2 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100">
                        @foreach($registros as $r)
                            <tr>
                                <td class="px-3 py-2">{{ $r->titulo ?? '—' }}</td>
                                <td class="px-3 py-2 text-xs text-ink-500">
                                    {{ \Illuminate\Support\Carbon::parse($r->creado_en)->format('d/m/Y H:i') }}
                                </td>
                                <td class="px-3 py-2 text-right text-xs space-x-2">
                                    @if(auth()->user()->tienePermiso('entidades.editar', $proyectoId))
                                        <button type="button" wire:click="abrirFormEditar({{ $r->id }})"
                                                class="text-ink-700 hover:underline">Editar</button>
                                    @endif
                                    @if(auth()->user()->tienePermiso('entidades.eliminar', $proyectoId))
                                        <button type="button" wire:click="eliminar({{ $r->id }})"
                                                wire:confirm="¿Eliminar este registro?"
                                                class="text-danger-600 hover:underline">Eliminar</button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    @endif
</div>
