<div class="space-y-6" @if($progreso !== null && $progreso->enCurso() && $progreso->estado->value === 'procesando') wire:poll.2s @endif>
    @if(session('importacion-ok'))
        <div class="rounded border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
            {{ session('importacion-ok') }}
        </div>
    @endif

    <section class="rounded-lg border border-gray-200 bg-white p-6 space-y-4">
        <div>
            <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-700">Importar personas</h3>
            <p class="text-xs text-gray-500 mt-1">
                Columnas esperadas: <code>tipo_persona, tipo_identificacion_codigo, identificacion, nombres, apellidos, razon_social, fecha_nacimiento</code>.
            </p>
        </div>

        @if($importacionId === null)
            <form wire:submit.prevent="guardarArchivo" class="space-y-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700">Archivo CSV</label>
                    <input type="file" wire:model="archivo" accept=".csv,text/csv"
                           class="mt-1 block w-full text-sm text-gray-700"/>
                    @error('archivo')<div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>@enderror
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Modo de importación</label>
                    <select wire:model="modo" class="mt-1 block w-full text-sm border-gray-300 rounded-md">
                        <option value="merge">merge — rellena solo campos vacíos en registros existentes</option>
                        <option value="skip_duplicados">skip_duplicados — ignora existentes (continúa el batch)</option>
                        <option value="overwrite">overwrite — pisa todos los campos en registros existentes</option>
                    </select>
                    <p class="text-[11px] text-gray-500 mt-1">
                        Aplica cuando una persona ya existe en el proyecto (mismo tipo + identificación).
                        Para nuevas personas, los tres modos insertan igual.
                    </p>
                </div>

                <div>
                    <button type="submit"
                            class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700">
                        Subir y validar
                    </button>
                </div>
            </form>
        @else
            @php $estadoActual = $progreso?->estado->value ?? '—'; @endphp

            <div class="space-y-3">
                <div class="grid grid-cols-1 sm:grid-cols-6 gap-3 text-sm">
                    <div class="rounded border border-gray-200 p-3">
                        <div class="text-[10px] uppercase text-gray-500">Archivo</div>
                        <div class="mt-1 font-medium text-gray-900 truncate">{{ $importacionActual->nombre_archivo ?? '—' }}</div>
                    </div>
                    <div class="rounded border border-gray-200 p-3">
                        <div class="text-[10px] uppercase text-gray-500">Total</div>
                        <div class="mt-1 font-semibold text-gray-900">{{ $progreso?->totalFilas ?? 0 }}</div>
                    </div>
                    <div class="rounded border border-emerald-200 bg-emerald-50 p-3">
                        <div class="text-[10px] uppercase text-emerald-700">Procesadas</div>
                        <div class="mt-1 font-semibold text-emerald-900">{{ $progreso?->procesadas ?? 0 }}</div>
                    </div>
                    <div class="rounded border border-blue-200 bg-blue-50 p-3">
                        <div class="text-[10px] uppercase text-blue-700">Válidas</div>
                        <div class="mt-1 font-semibold text-blue-900">{{ $progreso?->validas ?? 0 }}</div>
                    </div>
                    <div class="rounded border border-amber-200 bg-amber-50 p-3">
                        <div class="text-[10px] uppercase text-amber-700">Duplicadas</div>
                        <div class="mt-1 font-semibold text-amber-900">{{ $progreso?->duplicadas ?? 0 }}</div>
                    </div>
                    <div class="rounded border border-red-200 bg-red-50 p-3">
                        <div class="text-[10px] uppercase text-red-700">Inválidas</div>
                        <div class="mt-1 font-semibold text-red-900">{{ $progreso?->invalidas ?? 0 }}</div>
                    </div>
                </div>

                @if($progreso !== null)
                    <div class="space-y-1">
                        <div class="flex items-center justify-between text-xs text-gray-700">
                            <span>Estado: <code>{{ $estadoActual }}</code> · Modo: <code>{{ $progreso->modo->value }}</code></span>
                            <span class="font-mono">{{ $progreso->porcentaje() }}%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded h-2 overflow-hidden">
                            <div class="bg-blue-600 h-2 transition-all duration-500" style="width: {{ $progreso->porcentaje() }}%"></div>
                        </div>
                        @if($progreso->errorGlobal)
                            <div class="text-xs text-red-700 mt-1">Error: {{ $progreso->errorGlobal }}</div>
                        @endif
                    </div>
                @endif

                @if($estadoActual === 'preparada')
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Cambiar modo antes de procesar</label>
                        <select wire:model="modo" class="block w-full text-sm border-gray-300 rounded-md">
                            <option value="merge">merge — rellena solo campos vacíos</option>
                            <option value="skip_duplicados">skip_duplicados — ignora existentes</option>
                            <option value="overwrite">overwrite — pisa todos los campos</option>
                        </select>
                    </div>
                @endif

                <div class="flex items-center gap-2">
                    <label class="text-xs text-gray-600">Filtrar filas:</label>
                    <select wire:model.live="filtroFilas" class="text-xs border-gray-300 rounded">
                        <option value="todas">Todas</option>
                        <option value="pendiente">Pendientes</option>
                        <option value="procesada">Procesadas</option>
                        <option value="duplicada">Duplicadas</option>
                        <option value="invalida">Inválidas</option>
                        <option value="omitida">Omitidas</option>
                    </select>
                </div>

                <div class="rounded-md border border-gray-200 overflow-x-auto max-h-96 overflow-y-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-xs">
                        <thead class="bg-gray-50 uppercase tracking-wider text-gray-600 sticky top-0">
                            <tr>
                                <th class="px-2 py-2 text-left">#</th>
                                <th class="px-2 py-2 text-left">Estado</th>
                                <th class="px-2 py-2 text-left">Identificación</th>
                                <th class="px-2 py-2 text-left">Nombre / Razón</th>
                                <th class="px-2 py-2 text-left">Detalle</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($preview as $f)
                                @php
                                    $p = is_array($f->payload) ? $f->payload : json_decode($f->payload, true);
                                    $nombre = ($p['tipo_persona'] ?? null) === 'juridica'
                                        ? ($p['razon_social'] ?? '')
                                        : trim(($p['nombres'] ?? '').' '.($p['apellidos'] ?? ''));
                                    $badge = match ($f->estado) {
                                        'procesada' => 'bg-emerald-200 text-emerald-900',
                                        'duplicada' => 'bg-amber-100 text-amber-800',
                                        'invalida'  => 'bg-red-100 text-red-800',
                                        'omitida'   => 'bg-gray-200 text-gray-700',
                                        default     => 'bg-gray-100 text-gray-700',
                                    };
                                    $detalle = $f->mensaje_error ?: $f->razon_omision;
                                @endphp
                                <tr>
                                    <td class="px-2 py-1 font-mono">{{ $f->numero_fila }}</td>
                                    <td class="px-2 py-1">
                                        <span class="inline-block rounded px-1.5 py-0.5 text-[10px] {{ $badge }}">{{ $f->estado }}</span>
                                    </td>
                                    <td class="px-2 py-1 font-mono">
                                        {{ ($p['tipo_identificacion_codigo'] ?? '') }}
                                        {{ $p['identificacion'] ?? '' }}
                                    </td>
                                    <td class="px-2 py-1">{{ $nombre }}</td>
                                    <td class="px-2 py-1 text-gray-700">{{ $detalle }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex items-center justify-end gap-2">
                    @if($estadoActual === 'preparada')
                        <button type="button" wire:click="cerrar"
                                class="px-3 py-1.5 text-xs text-gray-700 border border-gray-300 rounded hover:bg-gray-50">
                            Descartar
                        </button>
                        <button type="button" wire:click="confirmar"
                                wire:confirm="¿Confirmar importación con modo {{ $modo }}? El proceso correrá en segundo plano."
                                class="px-3 py-1.5 text-xs text-white bg-blue-600 rounded hover:bg-blue-700">
                            Procesar en segundo plano
                        </button>
                    @elseif($estadoActual === 'procesando')
                        <button type="button" wire:click="cancelar"
                                wire:confirm="¿Cancelar la importación en curso?"
                                class="px-3 py-1.5 text-xs text-white bg-red-600 rounded hover:bg-red-700">
                            Cancelar importación
                        </button>
                    @elseif(in_array($estadoActual, ['completada', 'fallida', 'cancelada']))
                        <button type="button" wire:click="cerrar"
                                class="px-3 py-1.5 text-xs text-white bg-emerald-600 rounded hover:bg-emerald-700">
                            Cerrar
                        </button>
                    @endif
                </div>
            </div>
        @endif
    </section>

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
                            <td class="px-3 py-2 text-xs"><code>{{ $h->modo }}</code></td>
                            <td class="px-3 py-2 text-xs">{{ $h->usuario_nombre ?? '—' }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format($h->total_filas) }}</td>
                            <td class="px-3 py-2 text-right font-mono text-emerald-700">{{ number_format($h->procesadas) }}</td>
                            <td class="px-3 py-2 text-right font-mono text-amber-700">{{ number_format($h->duplicadas) }}</td>
                            <td class="px-3 py-2 text-right font-mono text-red-700">{{ number_format($h->invalidas) }}</td>
                            <td class="px-3 py-2 text-xs"><code>{{ $h->estado }}</code></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

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
