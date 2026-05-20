<div class="space-y-6"
     @if($progreso !== null && $progreso->enCurso() && $progreso->estado->value === 'procesando') wire:poll.2s @endif>

    {{-- Stepper --}}
    <ol class="flex items-center gap-2 text-xs">
        @foreach([1 => 'Subir archivo', 2 => 'Mapear columnas', 3 => 'Revisar', 4 => 'Procesar'] as $idx => $nombre)
            <li class="flex items-center gap-2">
                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full text-[11px] font-semibold
                            {{ $paso === $idx ? 'bg-brand-600 text-white' : ($paso > $idx ? 'bg-success-500 text-white' : 'bg-ink-200 text-ink-600') }}">
                    {{ $idx }}
                </span>
                <span class="{{ $paso === $idx ? 'font-semibold text-ink-900' : 'text-ink-600' }}">{{ $nombre }}</span>
                @if(! $loop->last)<span class="text-ink-300">→</span>@endif
            </li>
        @endforeach
    </ol>

    {{-- PASO 1: subir --}}
    @if($paso === 1)
        <section class="rounded-lg border border-ink-200 bg-white p-6 space-y-4">
            <h3 class="text-sm font-semibold uppercase tracking-wider text-ink-700">¿Qué deseas importar?</h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                @foreach($targetsDisponibles as $t)
                    <label class="flex cursor-pointer items-center gap-3 rounded border p-3 text-sm
                                  {{ $targetValor === $t->value ? 'border-brand-500 bg-brand-50' : 'border-ink-200 hover:border-ink-300' }}">
                        <input type="radio" wire:model.live="targetValor" value="{{ $t->value }}" class="text-brand-600"/>
                        <span class="font-medium text-ink-900">{{ $t->etiqueta() }}</span>
                    </label>
                @endforeach
            </div>
            @error('targetValor')<div class="text-xs text-danger-600">{{ $message }}</div>@enderror

            <hr class="border-ink-100"/>

            <form wire:submit.prevent="subirArchivo"
                  x-data="{ dragging: false, fileName: '', fileSize: '', uploading: false, formatSize(bytes) { if (!bytes) return ''; const kb = bytes / 1024; return kb < 1024 ? Math.round(kb) + ' KB' : (kb / 1024).toFixed(1) + ' MB'; } }"
                  @dragover.prevent="dragging = true"
                  @dragleave.prevent="dragging = false"
                  @drop.prevent="dragging = false; if ($event.dataTransfer.files.length) { $refs.fileInput.files = $event.dataTransfer.files; $refs.fileInput.dispatchEvent(new Event('change', { bubbles: true })); }"
                  @window:livewire-upload-start="uploading = true"
                  @window:livewire-upload-finish="uploading = false"
                  @window:livewire-upload-error="uploading = false"
                  class="space-y-3">

                {{-- Dropzone area --}}
                <div class="relative cursor-pointer rounded-lg border-2 border-dashed border-ink-300 bg-surface-50 p-6 text-center transition-all duration-fast ease-ui hover:border-brand-400 hover:bg-brand-50"
                     :class="{ '!border-brand-500 !bg-brand-50': dragging }"
                     @click="$refs.fileInput.click()">

                    <input x-ref="fileInput"
                           type="file"
                           wire:model="archivo"
                           accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                           class="hidden"
                           @change="fileName = $event.target.files[0]?.name || ''; fileSize = ($event.target.files[0]?.size || 0); uploading = true"/>

                    {{-- Estado: dragging --}}
                    <div x-show="dragging" class="pointer-events-none">
                        <svg class="mx-auto h-8 w-8 text-brand-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3"/>
                        </svg>
                        <div class="text-sm font-medium text-brand-700">Suelta el archivo aquí</div>
                    </div>

                    {{-- Estado: archivo cargado --}}
                    <template x-if="!dragging && fileName">
                        <div class="pointer-events-none">
                            <svg class="mx-auto h-8 w-8 text-success-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <div class="text-sm font-medium text-ink-900" x-text="fileName"></div>
                            <div class="text-xs text-ink-500 mt-1" x-text="formatSize(fileSize)"></div>
                        </div>
                    </template>

                    {{-- Estado: default --}}
                    <template x-if="!dragging && !fileName">
                        <div class="pointer-events-none">
                            <svg class="mx-auto h-8 w-8 text-ink-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3"/>
                            </svg>
                            <div class="text-sm font-medium text-ink-700">Arrastra tu archivo aquí o <span class="text-brand-600 underline">selecciona</span></div>
                            <div class="text-xs text-ink-500 mt-1">CSV o XLSX</div>
                        </div>
                    </template>
                </div>

                @error('archivo')<div class="text-xs text-danger-600">{{ $message }}</div>@enderror

                <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-brand-600 text-white text-sm font-medium rounded-md hover:bg-brand-700 disabled:opacity-50"
                        :disabled="$targetValor === null || uploading"
                        wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="subirArchivo">Continuar al mapeo</span>
                    <span wire:loading wire:target="subirArchivo">Procesando...</span>
                </button>
            </form>
        </section>
    @endif

    {{-- PASO 2: mapeo --}}
    @if($paso === 2 && $target !== null)
        <section class="rounded-lg border border-ink-200 bg-white p-6 space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-semibold uppercase tracking-wider text-ink-700">Mapeo de columnas</h3>
                    <p class="text-xs text-ink-500 mt-1">
                        Importando: <strong>{{ $target->etiqueta() }}</strong>
                        · {{ count($cabecerasCsv) }} columnas detectadas en el archivo.
                    </p>
                </div>
                <button type="button" wire:click="autoMapear"
                        class="inline-flex items-center gap-1 rounded border border-ink-300 bg-white px-3 py-1.5 text-xs hover:bg-ink-50">
                    Mapeo automático
                </button>
            </div>

            @php
                $basicos = collect($camposSistema)->filter(fn ($c) => ! $c->avanzado)->values();
                $avanzados = collect($camposSistema)->filter(fn ($c) => $c->avanzado)->values();
            @endphp

            <div class="overflow-x-auto rounded border border-ink-200">
                <table class="min-w-full divide-y divide-ink-200 text-sm">
                    <thead class="bg-ink-50 text-xs uppercase tracking-wider text-ink-600">
                        <tr>
                            <th class="px-3 py-2 text-left">Campo del sistema</th>
                            <th class="px-3 py-2 text-left">Columna del archivo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100">
                        @foreach($basicos as $campo)
                            @include('importaciones::livewire.partials.fila-mapeo', ['campo' => $campo, 'cabecerasCsv' => $cabecerasCsv])
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($avanzados->isNotEmpty())
                <div>
                    <button type="button" wire:click="$toggle('mostrarAvanzados')"
                            class="inline-flex items-center gap-1 text-xs text-brand-700 hover:underline">
                        {{ $mostrarAvanzados ? '▾ Ocultar' : '▸ Mostrar' }} campos avanzados ({{ $avanzados->count() }})
                    </button>
                </div>

                @if($mostrarAvanzados)
                    <div class="overflow-x-auto rounded border border-ink-200">
                        <table class="min-w-full divide-y divide-ink-200 text-sm">
                            <thead class="bg-ink-100 text-xs uppercase tracking-wider text-ink-600">
                                <tr>
                                    <th class="px-3 py-2 text-left">Campo avanzado</th>
                                    <th class="px-3 py-2 text-left">Columna del archivo</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-ink-100">
                                @foreach($avanzados as $campo)
                                    @include('importaciones::livewire.partials.fila-mapeo', ['campo' => $campo, 'cabecerasCsv' => $cabecerasCsv])
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @endif

            <details class="rounded border border-ink-200 bg-ink-50 p-3 text-xs">
                <summary class="cursor-pointer font-medium text-ink-700">Vista previa del archivo (5 primeras filas)</summary>
                <div class="mt-2 overflow-x-auto">
                    <table class="min-w-full text-xs">
                        <thead class="bg-white text-ink-600">
                            <tr>
                                @foreach($cabecerasCsv as $h)<th class="px-2 py-1 text-left font-mono">{{ $h }}</th>@endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-200">
                            @foreach($filasMuestra as $fila)
                                <tr>
                                    @foreach($fila as $valor)<td class="px-2 py-1 text-ink-800">{{ $valor }}</td>@endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>

            <div class="flex items-center justify-end gap-2">
                <button type="button" wire:click="volverASubir"
                        class="px-3 py-1.5 text-xs text-ink-700 border border-ink-300 rounded hover:bg-ink-50">
                    Volver
                </button>
                <button type="button" wire:click="confirmarMapeo"
                        class="px-3 py-1.5 text-xs text-white bg-brand-600 rounded hover:bg-brand-700">
                    Validar y continuar
                </button>
            </div>
        </section>
    @endif

    {{-- PASO 3: preview/confirmar --}}
    @if($paso === 3 && $progreso !== null)
        @php $estadoActual = $progreso->estado->value; @endphp

        <section class="rounded-lg border border-ink-200 bg-white p-6 space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-6 gap-3 text-sm">
                <div class="rounded border border-ink-200 p-3">
                    <div class="text-[10px] uppercase text-ink-500">Archivo</div>
                    <div class="mt-1 font-medium text-ink-900 truncate">{{ $importacionActual->nombre_archivo ?? '—' }}</div>
                </div>
                <div class="rounded border border-ink-200 p-3">
                    <div class="text-[10px] uppercase text-ink-500">Total</div>
                    <div class="mt-1 font-semibold text-ink-900">{{ $progreso->totalFilas }}</div>
                </div>
                <div class="rounded border border-brand-200 bg-brand-50 p-3">
                    <div class="text-[10px] uppercase text-brand-700">Válidas</div>
                    <div class="mt-1 font-semibold text-brand-900">{{ $progreso->validas }}</div>
                </div>
                <div class="rounded border border-danger-200 bg-danger-50 p-3">
                    <div class="text-[10px] uppercase text-danger-700">Inválidas</div>
                    <div class="mt-1 font-semibold text-danger-700">{{ $progreso->invalidas }}</div>
                </div>
                <div class="rounded border border-warning-200 bg-warning-50 p-3">
                    <div class="text-[10px] uppercase text-warning-700">Duplicadas</div>
                    <div class="mt-1 font-semibold text-warning-700">{{ $progreso->duplicadas }}</div>
                </div>
                <div class="rounded border border-success-200 bg-success-50 p-3">
                    <div class="text-[10px] uppercase text-success-700">Procesadas</div>
                    <div class="mt-1 font-semibold text-success-700">{{ $progreso->procesadas }}</div>
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-ink-700 mb-1">Modo de importación</label>
                <select wire:model="modo" class="block w-full text-sm border-ink-300 rounded-md">
                    <option value="merge">merge — rellena solo campos vacíos en registros existentes</option>
                    <option value="skip_duplicados">skip_duplicados — ignora existentes (continúa el batch)</option>
                    <option value="overwrite">overwrite — pisa todos los campos en registros existentes</option>
                </select>
            </div>

            <div class="flex items-center gap-2">
                <label class="text-xs text-ink-600">Filtrar filas:</label>
                <select wire:model.live="filtroFilas" class="text-xs border-ink-300 rounded">
                    <option value="todas">Todas</option>
                    <option value="pendiente">Válidas (pendientes)</option>
                    <option value="invalida">Inválidas</option>
                </select>
            </div>

            <div class="rounded-md border border-ink-200 overflow-x-auto max-h-96 overflow-y-auto">
                <table class="min-w-full divide-y divide-ink-200 text-xs">
                    <thead class="bg-ink-50 uppercase tracking-wider text-ink-600 sticky top-0">
                        <tr>
                            <th class="px-2 py-2 text-left">#</th>
                            <th class="px-2 py-2 text-left">Estado</th>
                            <th class="px-2 py-2 text-left">Detalle</th>
                            <th class="px-2 py-2 text-left">Payload (canónico)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100">
                        @foreach($preview as $f)
                            @php
                                $p = is_array($f->payload) ? $f->payload : json_decode($f->payload, true);
                                $badge = match ($f->estado) {
                                    'procesada' => 'bg-success-50 text-success-700',
                                    'duplicada' => 'bg-warning-50 text-warning-700',
                                    'invalida'  => 'bg-danger-50 text-danger-700',
                                    'omitida'   => 'bg-ink-200 text-ink-700',
                                    default     => 'bg-brand-100 text-brand-700',
                                };
                                $detalle = $f->mensaje_error ?: ($f->razon_omision ?? '');
                            @endphp
                            <tr>
                                <td class="px-2 py-1 font-mono">{{ $f->numero_fila }}</td>
                                <td class="px-2 py-1">
                                    <span class="inline-block rounded px-1.5 py-0.5 text-[10px] {{ $badge }}">{{ $f->estado }}</span>
                                </td>
                                <td class="px-2 py-1 text-ink-700 break-all">{{ $detalle }}</td>
                                <td class="px-2 py-1 font-mono text-[10px] text-ink-600 break-all">{{ json_encode($p, JSON_UNESCAPED_UNICODE) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex items-center justify-end gap-2">
                <button type="button" wire:click="cerrar"
                        class="px-3 py-1.5 text-xs text-ink-700 border border-ink-300 rounded hover:bg-ink-50">
                    Descartar
                </button>
                <button type="button" wire:click="procesar"
                        wire:confirm="¿Confirmar importación con modo {{ $modo }}? El proceso correrá en segundo plano."
                        class="px-3 py-1.5 text-xs text-white bg-brand-600 rounded hover:bg-brand-700"
                        @disabled($estadoActual !== 'preparada' || (int) $progreso->validas === 0)>
                    Procesar en segundo plano
                </button>
            </div>
        </section>
    @endif

    {{-- PASO 4: procesando --}}
    @if($paso === 4 && $progreso !== null)
        @php $estadoActual = $progreso->estado->value; @endphp
        <section class="rounded-lg border border-ink-200 bg-white p-6 space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-6 gap-3 text-sm">
                <div class="rounded border border-ink-200 p-3">
                    <div class="text-[10px] uppercase text-ink-500">Estado</div>
                    <div class="mt-1 font-mono text-ink-900">{{ $estadoActual }}</div>
                </div>
                <div class="rounded border border-ink-200 p-3">
                    <div class="text-[10px] uppercase text-ink-500">Total</div>
                    <div class="mt-1 font-semibold text-ink-900">{{ $progreso->totalFilas }}</div>
                </div>
                <div class="rounded border border-success-200 bg-success-50 p-3">
                    <div class="text-[10px] uppercase text-success-700">Procesadas</div>
                    <div class="mt-1 font-semibold text-success-700">{{ $progreso->procesadas }}</div>
                </div>
                <div class="rounded border border-warning-200 bg-warning-50 p-3">
                    <div class="text-[10px] uppercase text-warning-700">Duplicadas</div>
                    <div class="mt-1 font-semibold text-warning-700">{{ $progreso->duplicadas }}</div>
                </div>
                <div class="rounded border border-danger-200 bg-danger-50 p-3">
                    <div class="text-[10px] uppercase text-danger-700">Inválidas</div>
                    <div class="mt-1 font-semibold text-danger-700">{{ $progreso->invalidas }}</div>
                </div>
                <div class="rounded border border-ink-200 p-3">
                    <div class="text-[10px] uppercase text-ink-500">Modo</div>
                    <div class="mt-1 font-mono text-ink-900">{{ $progreso->modo->value }}</div>
                </div>
            </div>

            <div class="space-y-1">
                <div class="flex items-center justify-between text-xs text-ink-700">
                    <span>Progreso</span>
                    <span class="font-mono">{{ $progreso->porcentaje() }}%</span>
                </div>
                <div class="w-full bg-ink-200 rounded h-2 overflow-hidden">
                    <div class="bg-brand-600 h-2 transition-all duration-500" style="width: {{ $progreso->porcentaje() }}%"></div>
                </div>
                @if($progreso->errorGlobal)
                    <div class="text-xs text-danger-700 mt-1">Error: {{ $progreso->errorGlobal }}</div>
                @endif
            </div>

            <div class="flex items-center justify-end gap-2">
                @if($estadoActual === 'procesando')
                    <button type="button" wire:click="cancelar"
                            wire:confirm="¿Cancelar la importación en curso?"
                            class="px-3 py-1.5 text-xs text-white bg-danger-600 rounded hover:bg-danger-700">
                        Cancelar
                    </button>
                @elseif(in_array($estadoActual, ['completada', 'fallida', 'cancelada'], true))
                    <button type="button" wire:click="cerrar"
                            class="px-3 py-1.5 text-xs text-white bg-success-600 rounded hover:bg-success-700">
                        Nueva importación
                    </button>
                @endif
            </div>
        </section>
    @endif

    {{-- Historial --}}
    <section class="rounded-lg border border-ink-200 bg-white overflow-hidden">
        <div class="px-4 py-3 border-b border-ink-200 bg-ink-50 text-xs font-semibold uppercase tracking-wider text-ink-600">
            Historial de importaciones ({{ $historial->count() }})
        </div>
        @if($historial->isEmpty())
            <div class="p-6 text-sm text-ink-500 text-center">Aún no hay importaciones en este proyecto.</div>
        @else
            <table class="min-w-full divide-y divide-ink-200 text-sm">
                <thead class="bg-ink-50 text-xs uppercase tracking-wider text-ink-600">
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
                <tbody class="divide-y divide-ink-100">
                    @foreach($historial as $h)
                        <tr>
                            <td class="px-3 py-2 text-xs">{{ \Illuminate\Support\Carbon::parse($h->creada_en)->format('d/m/Y H:i') }}</td>
                            <td class="px-3 py-2 text-xs">{{ $h->nombre_archivo }}</td>
                            <td class="px-3 py-2 text-xs"><code>{{ $h->tipo_entidad }}</code></td>
                            <td class="px-3 py-2 text-xs"><code>{{ $h->modo }}</code></td>
                            <td class="px-3 py-2 text-xs">{{ $h->usuario_nombre ?? '—' }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format((int) $h->total_filas) }}</td>
                            <td class="px-3 py-2 text-right font-mono text-success-700">{{ number_format((int) $h->procesadas) }}</td>
                            <td class="px-3 py-2 text-right font-mono text-warning-700">{{ number_format((int) $h->duplicadas) }}</td>
                            <td class="px-3 py-2 text-right font-mono text-danger-700">{{ number_format((int) $h->invalidas) }}</td>
                            <td class="px-3 py-2 text-xs"><code>{{ $h->estado }}</code></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    {{-- Exportaciones --}}
    <section class="rounded-lg border border-brand-200 bg-brand-50 p-4 space-y-3">
        <div class="text-sm font-semibold text-brand-900">Exportaciones CSV del proyecto</div>
        @php $pid = app('tenancy.proyecto_activo')->id; @endphp
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2 text-xs">
            <a href="{{ route('proyectos.importaciones.exportar-personas', ['proyecto_id' => $pid]) }}"
               class="inline-flex items-center justify-center px-3 py-2 text-white bg-brand-600 rounded hover:bg-brand-700">Personas</a>
            <a href="{{ route('proyectos.importaciones.exportar-casos', ['proyecto_id' => $pid]) }}"
               class="inline-flex items-center justify-center px-3 py-2 text-white bg-brand-600 rounded hover:bg-brand-700">Casos</a>
            <a href="{{ route('proyectos.importaciones.exportar-gestiones', ['proyecto_id' => $pid]) }}"
               class="inline-flex items-center justify-center px-3 py-2 text-white bg-brand-600 rounded hover:bg-brand-700">Gestiones</a>
            <a href="{{ route('proyectos.importaciones.exportar-compromisos', ['proyecto_id' => $pid]) }}"
               class="inline-flex items-center justify-center px-3 py-2 text-white bg-brand-600 rounded hover:bg-brand-700">Compromisos</a>
        </div>
    </section>
</div>
