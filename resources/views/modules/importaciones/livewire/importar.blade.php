<div class="space-y-6"
     @if($progreso !== null && $progreso->enCurso() && $progreso->estado->value === 'procesando') wire:poll.2s @endif>

    {{-- Stepper --}}
    <ol class="flex items-center gap-2 text-xs">
        @foreach([1 => 'Subir CSV', 2 => 'Mapear columnas', 3 => 'Revisar', 4 => 'Procesar'] as $idx => $nombre)
            <li class="flex items-center gap-2">
                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full text-[11px] font-semibold
                            {{ $paso === $idx ? 'bg-blue-600 text-white' : ($paso > $idx ? 'bg-emerald-500 text-white' : 'bg-gray-200 text-gray-600') }}">
                    {{ $idx }}
                </span>
                <span class="{{ $paso === $idx ? 'font-semibold text-gray-900' : 'text-gray-600' }}">{{ $nombre }}</span>
                @if(! $loop->last)<span class="text-gray-300">→</span>@endif
            </li>
        @endforeach
    </ol>

    {{-- PASO 1: subir --}}
    @if($paso === 1)
        <section class="rounded-lg border border-gray-200 bg-white p-6 space-y-4">
            <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-700">¿Qué deseas importar?</h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                @foreach($targetsDisponibles as $t)
                    <label class="flex cursor-pointer items-center gap-3 rounded border p-3 text-sm
                                  {{ $targetValor === $t->value ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300' }}">
                        <input type="radio" wire:model.live="targetValor" value="{{ $t->value }}" class="text-blue-600"/>
                        <span class="font-medium text-gray-900">{{ $t->etiqueta() }}</span>
                    </label>
                @endforeach
            </div>
            @error('targetValor')<div class="text-xs text-red-600">{{ $message }}</div>@enderror

            <hr class="border-gray-100"/>

            <form wire:submit.prevent="subirArchivo" class="space-y-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700">Archivo CSV</label>
                    <input type="file" wire:model="archivo" accept=".csv,text/csv"
                           class="mt-1 block w-full text-sm text-gray-700"/>
                    @error('archivo')<div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>@enderror
                    <p class="text-[11px] text-gray-500 mt-1">
                        Cualquier nombre de columna funciona. En el siguiente paso vincularás cada columna del archivo a un campo del sistema.
                    </p>
                </div>

                <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 disabled:opacity-50"
                        @disabled($targetValor === null)>
                    Continuar al mapeo
                </button>
            </form>
        </section>
    @endif

    {{-- PASO 2: mapeo --}}
    @if($paso === 2 && $target !== null)
        <section class="rounded-lg border border-gray-200 bg-white p-6 space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-700">Mapeo de columnas</h3>
                    <p class="text-xs text-gray-500 mt-1">
                        Importando: <strong>{{ $target->etiqueta() }}</strong>
                        · {{ count($cabecerasCsv) }} columnas detectadas en el archivo.
                    </p>
                </div>
                <button type="button" wire:click="autoMapear"
                        class="inline-flex items-center gap-1 rounded border border-gray-300 bg-white px-3 py-1.5 text-xs hover:bg-gray-50">
                    Mapeo automático
                </button>
            </div>

            <div class="overflow-x-auto rounded border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-600">
                        <tr>
                            <th class="px-3 py-2 text-left">Campo del sistema</th>
                            <th class="px-3 py-2 text-left">Columna del CSV</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($camposSistema as $campo)
                            <tr>
                                <td class="px-3 py-2 align-top">
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium text-gray-900">{{ $campo->etiqueta }}</span>
                                        @if($campo->requerido)
                                            <span class="rounded bg-red-100 px-1.5 py-0.5 text-[10px] font-semibold text-red-700">requerido</span>
                                        @else
                                            <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] text-gray-600">opcional</span>
                                        @endif
                                    </div>
                                    @if($campo->descripcion)
                                        <div class="text-[11px] text-gray-500 mt-0.5">{{ $campo->descripcion }}</div>
                                    @endif
                                    <div class="text-[10px] text-gray-400 mt-0.5 font-mono">{{ $campo->codigo }}</div>
                                </td>
                                <td class="px-3 py-2 align-top">
                                    <select wire:model="mapeo.{{ $campo->codigo }}"
                                            class="block w-full text-sm border-gray-300 rounded">
                                        <option value="">— No mapear —</option>
                                        @foreach($cabecerasCsv as $h)
                                            <option value="{{ $h }}">{{ $h }}</option>
                                        @endforeach
                                    </select>
                                    @error("mapeo.{$campo->codigo}")<div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>@enderror
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <details class="rounded border border-gray-200 bg-gray-50 p-3 text-xs">
                <summary class="cursor-pointer font-medium text-gray-700">Vista previa del archivo (5 primeras filas)</summary>
                <div class="mt-2 overflow-x-auto">
                    <table class="min-w-full text-xs">
                        <thead class="bg-white text-gray-600">
                            <tr>
                                @foreach($cabecerasCsv as $h)<th class="px-2 py-1 text-left font-mono">{{ $h }}</th>@endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach($filasMuestra as $fila)
                                <tr>
                                    @foreach($fila as $valor)<td class="px-2 py-1 text-gray-800">{{ $valor }}</td>@endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>

            <div class="flex items-center justify-end gap-2">
                <button type="button" wire:click="volverASubir"
                        class="px-3 py-1.5 text-xs text-gray-700 border border-gray-300 rounded hover:bg-gray-50">
                    Volver
                </button>
                <button type="button" wire:click="confirmarMapeo"
                        class="px-3 py-1.5 text-xs text-white bg-blue-600 rounded hover:bg-blue-700">
                    Validar y continuar
                </button>
            </div>
        </section>
    @endif

    {{-- PASO 3: preview/confirmar --}}
    @if($paso === 3 && $progreso !== null)
        @php $estadoActual = $progreso->estado->value; @endphp

        <section class="rounded-lg border border-gray-200 bg-white p-6 space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-6 gap-3 text-sm">
                <div class="rounded border border-gray-200 p-3">
                    <div class="text-[10px] uppercase text-gray-500">Archivo</div>
                    <div class="mt-1 font-medium text-gray-900 truncate">{{ $importacionActual->nombre_archivo ?? '—' }}</div>
                </div>
                <div class="rounded border border-gray-200 p-3">
                    <div class="text-[10px] uppercase text-gray-500">Total</div>
                    <div class="mt-1 font-semibold text-gray-900">{{ $progreso->totalFilas }}</div>
                </div>
                <div class="rounded border border-blue-200 bg-blue-50 p-3">
                    <div class="text-[10px] uppercase text-blue-700">Válidas</div>
                    <div class="mt-1 font-semibold text-blue-900">{{ $progreso->validas }}</div>
                </div>
                <div class="rounded border border-red-200 bg-red-50 p-3">
                    <div class="text-[10px] uppercase text-red-700">Inválidas</div>
                    <div class="mt-1 font-semibold text-red-900">{{ $progreso->invalidas }}</div>
                </div>
                <div class="rounded border border-amber-200 bg-amber-50 p-3">
                    <div class="text-[10px] uppercase text-amber-700">Duplicadas</div>
                    <div class="mt-1 font-semibold text-amber-900">{{ $progreso->duplicadas }}</div>
                </div>
                <div class="rounded border border-emerald-200 bg-emerald-50 p-3">
                    <div class="text-[10px] uppercase text-emerald-700">Procesadas</div>
                    <div class="mt-1 font-semibold text-emerald-900">{{ $progreso->procesadas }}</div>
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Modo de importación</label>
                <select wire:model="modo" class="block w-full text-sm border-gray-300 rounded-md">
                    <option value="merge">merge — rellena solo campos vacíos en registros existentes</option>
                    <option value="skip_duplicados">skip_duplicados — ignora existentes (continúa el batch)</option>
                    <option value="overwrite">overwrite — pisa todos los campos en registros existentes</option>
                </select>
            </div>

            <div class="flex items-center gap-2">
                <label class="text-xs text-gray-600">Filtrar filas:</label>
                <select wire:model.live="filtroFilas" class="text-xs border-gray-300 rounded">
                    <option value="todas">Todas</option>
                    <option value="pendiente">Válidas (pendientes)</option>
                    <option value="invalida">Inválidas</option>
                </select>
            </div>

            <div class="rounded-md border border-gray-200 overflow-x-auto max-h-96 overflow-y-auto">
                <table class="min-w-full divide-y divide-gray-200 text-xs">
                    <thead class="bg-gray-50 uppercase tracking-wider text-gray-600 sticky top-0">
                        <tr>
                            <th class="px-2 py-2 text-left">#</th>
                            <th class="px-2 py-2 text-left">Estado</th>
                            <th class="px-2 py-2 text-left">Detalle</th>
                            <th class="px-2 py-2 text-left">Payload (canónico)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($preview as $f)
                            @php
                                $p = is_array($f->payload) ? $f->payload : json_decode($f->payload, true);
                                $badge = match ($f->estado) {
                                    'procesada' => 'bg-emerald-200 text-emerald-900',
                                    'duplicada' => 'bg-amber-100 text-amber-800',
                                    'invalida'  => 'bg-red-100 text-red-800',
                                    'omitida'   => 'bg-gray-200 text-gray-700',
                                    default     => 'bg-blue-100 text-blue-700',
                                };
                                $detalle = $f->mensaje_error ?: ($f->razon_omision ?? '');
                            @endphp
                            <tr>
                                <td class="px-2 py-1 font-mono">{{ $f->numero_fila }}</td>
                                <td class="px-2 py-1">
                                    <span class="inline-block rounded px-1.5 py-0.5 text-[10px] {{ $badge }}">{{ $f->estado }}</span>
                                </td>
                                <td class="px-2 py-1 text-gray-700 break-all">{{ $detalle }}</td>
                                <td class="px-2 py-1 font-mono text-[10px] text-gray-600 break-all">{{ json_encode($p, JSON_UNESCAPED_UNICODE) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex items-center justify-end gap-2">
                <button type="button" wire:click="cerrar"
                        class="px-3 py-1.5 text-xs text-gray-700 border border-gray-300 rounded hover:bg-gray-50">
                    Descartar
                </button>
                <button type="button" wire:click="procesar"
                        wire:confirm="¿Confirmar importación con modo {{ $modo }}? El proceso correrá en segundo plano."
                        class="px-3 py-1.5 text-xs text-white bg-blue-600 rounded hover:bg-blue-700"
                        @disabled($estadoActual !== 'preparada' || (int) $progreso->validas === 0)>
                    Procesar en segundo plano
                </button>
            </div>
        </section>
    @endif

    {{-- PASO 4: procesando --}}
    @if($paso === 4 && $progreso !== null)
        @php $estadoActual = $progreso->estado->value; @endphp
        <section class="rounded-lg border border-gray-200 bg-white p-6 space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-6 gap-3 text-sm">
                <div class="rounded border border-gray-200 p-3">
                    <div class="text-[10px] uppercase text-gray-500">Estado</div>
                    <div class="mt-1 font-mono text-gray-900">{{ $estadoActual }}</div>
                </div>
                <div class="rounded border border-gray-200 p-3">
                    <div class="text-[10px] uppercase text-gray-500">Total</div>
                    <div class="mt-1 font-semibold text-gray-900">{{ $progreso->totalFilas }}</div>
                </div>
                <div class="rounded border border-emerald-200 bg-emerald-50 p-3">
                    <div class="text-[10px] uppercase text-emerald-700">Procesadas</div>
                    <div class="mt-1 font-semibold text-emerald-900">{{ $progreso->procesadas }}</div>
                </div>
                <div class="rounded border border-amber-200 bg-amber-50 p-3">
                    <div class="text-[10px] uppercase text-amber-700">Duplicadas</div>
                    <div class="mt-1 font-semibold text-amber-900">{{ $progreso->duplicadas }}</div>
                </div>
                <div class="rounded border border-red-200 bg-red-50 p-3">
                    <div class="text-[10px] uppercase text-red-700">Inválidas</div>
                    <div class="mt-1 font-semibold text-red-900">{{ $progreso->invalidas }}</div>
                </div>
                <div class="rounded border border-gray-200 p-3">
                    <div class="text-[10px] uppercase text-gray-500">Modo</div>
                    <div class="mt-1 font-mono text-gray-900">{{ $progreso->modo->value }}</div>
                </div>
            </div>

            <div class="space-y-1">
                <div class="flex items-center justify-between text-xs text-gray-700">
                    <span>Progreso</span>
                    <span class="font-mono">{{ $progreso->porcentaje() }}%</span>
                </div>
                <div class="w-full bg-gray-200 rounded h-2 overflow-hidden">
                    <div class="bg-blue-600 h-2 transition-all duration-500" style="width: {{ $progreso->porcentaje() }}%"></div>
                </div>
                @if($progreso->errorGlobal)
                    <div class="text-xs text-red-700 mt-1">Error: {{ $progreso->errorGlobal }}</div>
                @endif
            </div>

            <div class="flex items-center justify-end gap-2">
                @if($estadoActual === 'procesando')
                    <button type="button" wire:click="cancelar"
                            wire:confirm="¿Cancelar la importación en curso?"
                            class="px-3 py-1.5 text-xs text-white bg-red-600 rounded hover:bg-red-700">
                        Cancelar
                    </button>
                @elseif(in_array($estadoActual, ['completada', 'fallida', 'cancelada'], true))
                    <button type="button" wire:click="cerrar"
                            class="px-3 py-1.5 text-xs text-white bg-emerald-600 rounded hover:bg-emerald-700">
                        Nueva importación
                    </button>
                @endif
            </div>
        </section>
    @endif

    {{-- Historial --}}
    <section class="rounded-lg border border-gray-200 bg-white overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200 bg-gray-50 text-xs font-semibold uppercase tracking-wider text-gray-600">
            Historial de importaciones ({{ $historial->count() }})
        </div>
        @if($historial->isEmpty())
            <div class="p-6 text-sm text-gray-500 text-center">Aún no hay importaciones en este proyecto.</div>
        @else
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-600">
                    <tr>
                        <th class="px-3 py-2 text-left">Fecha</th>
                        <th class="px-3 py-2 text-left">Archivo</th>
                        <th class="px-3 py-2 text-left">Tipo</th>
                        <th class="px-3 py-2 text-left">Modo</th>
                        <th class="px-3 py-2 text-left">Usuario</th>
                        <th class="px-3 py-2 text-right">Total</th>
                        <th class="px-3 py-2 text-right">Procesadas</th>
                        <th class="px-3 py-2 text-right">Duplicadas</th>
                        <th class="px-3 py-2 text-right">Inválidas</th>
                        <th class="px-3 py-2 text-left">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($historial as $h)
                        <tr>
                            <td class="px-3 py-2 text-xs">{{ \Illuminate\Support\Carbon::parse($h->creada_en)->format('d/m/Y H:i') }}</td>
                            <td class="px-3 py-2 text-xs">{{ $h->nombre_archivo }}</td>
                            <td class="px-3 py-2 text-xs"><code>{{ $h->tipo_entidad }}</code></td>
                            <td class="px-3 py-2 text-xs"><code>{{ $h->modo }}</code></td>
                            <td class="px-3 py-2 text-xs">{{ $h->usuario_nombre ?? '—' }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format((int) $h->total_filas) }}</td>
                            <td class="px-3 py-2 text-right font-mono text-emerald-700">{{ number_format((int) $h->procesadas) }}</td>
                            <td class="px-3 py-2 text-right font-mono text-amber-700">{{ number_format((int) $h->duplicadas) }}</td>
                            <td class="px-3 py-2 text-right font-mono text-red-700">{{ number_format((int) $h->invalidas) }}</td>
                            <td class="px-3 py-2 text-xs"><code>{{ $h->estado }}</code></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    {{-- Exportaciones --}}
    <section class="rounded-lg border border-blue-200 bg-blue-50 p-4 space-y-3">
        <div class="text-sm font-semibold text-blue-900">Exportaciones CSV del proyecto</div>
        @php $pid = app('tenancy.proyecto_activo')->id; @endphp
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2 text-xs">
            <a href="{{ route('proyectos.importaciones.exportar-personas', ['proyecto_id' => $pid]) }}"
               class="inline-flex items-center justify-center px-3 py-2 text-white bg-blue-600 rounded hover:bg-blue-700">Personas</a>
            <a href="{{ route('proyectos.importaciones.exportar-casos', ['proyecto_id' => $pid]) }}"
               class="inline-flex items-center justify-center px-3 py-2 text-white bg-blue-600 rounded hover:bg-blue-700">Casos</a>
            <a href="{{ route('proyectos.importaciones.exportar-gestiones', ['proyecto_id' => $pid]) }}"
               class="inline-flex items-center justify-center px-3 py-2 text-white bg-blue-600 rounded hover:bg-blue-700">Gestiones</a>
            <a href="{{ route('proyectos.importaciones.exportar-compromisos', ['proyecto_id' => $pid]) }}"
               class="inline-flex items-center justify-center px-3 py-2 text-white bg-blue-600 rounded hover:bg-blue-700">Compromisos</a>
        </div>
    </section>
</div>
