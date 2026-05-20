<div class="space-y-6"
     @if($progreso !== null && $progreso->enCurso() && $progreso->estado->value === 'procesando') wire:poll.2s @endif>

    {{-- Stepper --}}
    <ol class="flex items-center gap-2 text-xs">
        @foreach([1 => 'Subir archivo', 2 => 'Configurar columnas', 3 => 'Confirmar', 4 => 'Procesar'] as $idx => $nombre)
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

            @if($target !== null && $target !== \App\Modules\Importaciones\Domain\Enums\TargetImportacion::PERSONA)
                <div>
                    <label class="block text-xs font-medium text-ink-700 mb-1">Cartera</label>
                    <select wire:model.live="carteraId" class="block w-full max-w-sm text-sm border-ink-300 rounded-md">
                        <option value="">Selecciona una cartera…</option>
                        @foreach($carteras as $c)
                            <option value="{{ $c->id }}">{{ $c->nombre }} ({{ $c->codigo }})</option>
                        @endforeach
                    </select>
                    @error('carteraId')<div class="text-xs text-danger-600 mt-1">{{ $message }}</div>@enderror
                </div>
            @endif

            <hr class="border-ink-100"/>

            <form @submit.prevent="enviarFormulario"
                  x-data="{
                      dragging: false,
                      fileName: '',
                      fileSize: '',
                      uploading: false,
                      submitted: false,
                      formatSize(bytes) {
                          if (!bytes) return '';
                          const kb = bytes / 1024;
                          return kb < 1024 ? Math.round(kb) + ' KB' : (kb / 1024).toFixed(1) + ' MB';
                      },
                      seleccionarArchivo(event) {
                          const file = event.target.files[0];
                          if (!file) return;
                          this.fileName = file.name;
                          this.fileSize = file.size;
                          this.uploading = true;
                          this.submitted = false;
                          $wire.upload(
                              'archivo',
                              file,
                              () => { this.uploading = false; },
                              () => { this.uploading = false; this.submitted = false; },
                              () => {}
                          );
                      },
                      enviarFormulario() {
                          if (this.uploading || this.submitted) return;
                          if (!$wire.archivoListo) return;
                          this.submitted = true;
                          $wire.subirArchivo().catch(() => { this.submitted = false; });
                      }
                  }"
                  @dragover.prevent="dragging = true"
                  @dragleave.prevent="dragging = false"
                  @drop.prevent="
                      dragging = false;
                      if ($event.dataTransfer.files.length) {
                          $refs.fileInput.files = $event.dataTransfer.files;
                          $refs.fileInput.dispatchEvent(new Event('change', { bubbles: true }));
                      }
                  "
                  class="space-y-3">

                <div class="relative cursor-pointer rounded-lg border-2 border-dashed border-ink-300 bg-surface-50 p-6 text-center transition-all duration-fast ease-ui hover:border-brand-400 hover:bg-brand-50"
                     :class="{ '!border-brand-500 !bg-brand-50': dragging }"
                     @click="$refs.fileInput.click()">

                    <input x-ref="fileInput"
                           type="file"
                           accept=".csv,.xlsx,.xlsm,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel.sheet.macroEnabled.12"
                           class="hidden"
                           @change="seleccionarArchivo($event)"/>

                    <div x-show="dragging" class="pointer-events-none">
                        <svg class="mx-auto h-8 w-8 text-brand-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3"/>
                        </svg>
                        <div class="text-sm font-medium text-brand-700">Suelta el archivo aquí</div>
                    </div>

                    <div x-show="!dragging && uploading" class="pointer-events-none">
                        <svg class="mx-auto h-8 w-8 text-brand-400 mb-2 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        <div class="text-sm font-medium text-ink-600">Subiendo <span x-text="fileName"></span>…</div>
                        <div class="text-xs text-ink-400 mt-1" x-text="formatSize(fileSize)"></div>
                    </div>

                    <template x-if="!dragging && !uploading && fileName">
                        <div class="pointer-events-none">
                            <svg class="mx-auto h-8 w-8 text-success-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <div class="text-sm font-medium text-ink-900" x-text="fileName"></div>
                            <div class="text-xs text-ink-500 mt-1" x-text="formatSize(fileSize)"></div>
                        </div>
                    </template>

                    <template x-if="!dragging && !uploading && !fileName">
                        <div class="pointer-events-none">
                            <svg class="mx-auto h-8 w-8 text-ink-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3"/>
                            </svg>
                            <div class="text-sm font-medium text-ink-700">Arrastra tu archivo aquí o <span class="text-brand-600 underline">selecciona</span></div>
                            <div class="text-xs text-ink-500 mt-1">CSV, XLSX o XLSM · máx. 16 MB</div>
                        </div>
                    </template>
                </div>

                @error('archivo')<div class="text-xs text-danger-600">{{ $message }}</div>@enderror

                <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-brand-600 text-white text-sm font-medium rounded-md hover:bg-brand-700 disabled:opacity-50 disabled:cursor-not-allowed"
                        :disabled="uploading || submitted || !$wire.archivoListo"
                        wire:loading.attr="disabled"
                        wire:target="subirArchivo">
                    <span wire:loading.remove wire:target="subirArchivo">Continuar al mapeo</span>
                    <span wire:loading wire:target="subirArchivo">Procesando...</span>
                </button>

            </form>
        </section>
    @endif

    {{-- PASO 2: configurar columnas --}}
    @if($paso === 2 && count($columnas) > 0)
        <section class="rounded-lg border border-ink-200 bg-white p-6 space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-semibold uppercase tracking-wider text-ink-700">Configurar columnas</h3>
                    <p class="text-xs text-ink-500 mt-1">
                        Importando: <strong>{{ $target?->etiqueta() }}</strong>
                        · {{ count($columnas) }} columnas detectadas.
                    </p>
                </div>
            </div>

            @if(count($advertencias) > 0)
                <div class="rounded border border-warning-200 bg-warning-50 p-3 text-xs text-warning-800">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach($advertencias as $adv)
                            <li>{{ $adv }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="overflow-x-auto rounded border border-ink-200">
                <table class="min-w-full divide-y divide-ink-200 text-sm">
                    <thead class="bg-ink-50 text-xs uppercase tracking-wider text-ink-600">
                        <tr>
                            <th class="px-3 py-2 text-left">Columna del archivo</th>
                            <th class="px-3 py-2 text-left">Tipo inferido</th>
                            <th class="px-3 py-2 text-left">Acción</th>
                            <th class="px-3 py-2 text-center">¿Identificador persona?</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100">
                        @foreach($columnas as $col)
                            @php
                                $tipoBadge = match($col['tipo_inferido']) {
                                    'texto_corto' => 'bg-ink-100 text-ink-700',
                                    'texto_largo' => 'bg-ink-100 text-ink-700',
                                    'numero_entero' => 'bg-blue-100 text-blue-700',
                                    'numero_decimal' => 'bg-blue-100 text-blue-700',
                                    'fecha' => 'bg-purple-100 text-purple-700',
                                    'fecha_hora' => 'bg-purple-100 text-purple-700',
                                    'booleano' => 'bg-green-100 text-green-700',
                                    'seleccion_unica' => 'bg-yellow-100 text-yellow-700',
                                    'moneda' => 'bg-emerald-100 text-emerald-700',
                                    default => 'bg-ink-100 text-ink-700',
                                };
                                $tipoLabel = match($col['tipo_inferido']) {
                                    'texto_corto' => 'Texto corto',
                                    'texto_largo' => 'Texto largo',
                                    'numero_entero' => 'Nº entero',
                                    'numero_decimal' => 'Nº decimal',
                                    'fecha' => 'Fecha',
                                    'fecha_hora' => 'Fecha/hora',
                                    'booleano' => 'Booleano',
                                    'seleccion_unica' => 'Selección',
                                    'moneda' => 'Moneda',
                                    default => $col['tipo_inferido'],
                                };
                            @endphp
                            <tr>
                                <td class="px-3 py-2 font-mono text-xs text-ink-800">{{ $col['nombre_original'] }}</td>
                                <td class="px-3 py-2">
                                    <span class="inline-block rounded px-1.5 py-0.5 text-[10px] font-medium {{ $tipoBadge }}">
                                        {{ $tipoLabel }}
                                    </span>
                                </td>
                                <td class="px-3 py-2">
                                    <select wire:model.live="columnas.{{ $loop->index }}.accion"
                                            wire:change="actualizarAccionColumna('{{ $col['nombre_original'] }}', $event.target.value)"
                                            class="text-xs border-ink-300 rounded">
                                        @if($col['campo_sistema_mapeado'])
                                            <option value="mapear_sistema" @selected($col['accion'] === 'mapear_sistema')>→ {{ $col['campo_sistema_mapeado'] }}</option>
                                        @endif
                                        <option value="crear_cp" @selected($col['accion'] === 'crear_cp')>Crear campo personalizado</option>
                                        <option value="ignorar" @selected($col['accion'] === 'ignorar')>Ignorar</option>
                                    </select>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <input type="radio"
                                           name="columna_identificador"
                                           wire:click="marcarComoIdentificador('{{ $col['nombre_original'] }}')"
                                           @checked($col['es_identificador_persona'])
                                           class="text-brand-600"/>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @php
                $mapeadasSistema = collect($columnas)->filter(fn($c) => $c['accion'] === 'mapear_sistema')->count();
                $crearCP = collect($columnas)->filter(fn($c) => $c['accion'] === 'crear_cp')->count();
                $ignoradas = collect($columnas)->filter(fn($c) => $c['accion'] === 'ignorar')->count();
                $tieneId = collect($columnas)->filter(fn($c) => $c['es_identificador_persona'])->count() > 0;
            @endphp

            <div class="flex items-center gap-4 text-xs text-ink-600">
                <span class="inline-flex items-center gap-1">
                    <span class="inline-block w-2 h-2 rounded-full bg-brand-500"></span>
                    {{ $mapeadasSistema }} mapeadas al sistema
                </span>
                <span class="inline-flex items-center gap-1">
                    <span class="inline-block w-2 h-2 rounded-full bg-success-500"></span>
                    {{ $crearCP }} nuevas como CP
                </span>
                <span class="inline-flex items-center gap-1">
                    <span class="inline-block w-2 h-2 rounded-full bg-ink-300"></span>
                    {{ $ignoradas }} ignoradas
                </span>
                @if(! $tieneId)
                    <span class="text-warning-700 font-medium">⚠ Ninguna columna marcada como identificador</span>
                @endif
            </div>

            <div class="flex items-center justify-end gap-2">
                <button type="button" wire:click="cerrar"
                        class="px-3 py-1.5 text-xs text-ink-700 border border-ink-300 rounded hover:bg-ink-50">
                    Descartar
                </button>
                <button type="button" wire:click="confirmarMapeo"
                        class="px-3 py-1.5 text-xs text-white bg-brand-600 rounded hover:bg-brand-700">
                    Validar y continuar
                </button>
            </div>
            @error('columnas')<div class="text-xs text-danger-600">{{ $message }}</div>@enderror
        </section>
    @endif

    {{-- PASO 3: confirmar --}}
    @if($paso === 3 && $resultadoDryRun !== null)
        <section class="rounded-lg border border-ink-200 bg-white p-6 space-y-4">
            <h3 class="text-sm font-semibold uppercase tracking-wider text-ink-700">Confirmar importación</h3>

            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-sm">
                <div class="rounded border border-ink-200 p-3">
                    <div class="text-[10px] uppercase text-ink-500">Target</div>
                    <div class="mt-1 font-medium text-ink-900">{{ $target?->etiqueta() }}</div>
                </div>
                <div class="rounded border border-ink-200 p-3">
                    <div class="text-[10px] uppercase text-ink-500">Total filas</div>
                    <div class="mt-1 font-semibold text-ink-900">{{ $resultadoDryRun['filasTotales'] }}</div>
                </div>
                <div class="rounded border border-brand-200 bg-brand-50 p-3">
                    <div class="text-[10px] uppercase text-brand-700">Modo</div>
                    <div class="mt-1 font-semibold text-brand-900">{{ collect(\App\Modules\Importaciones\Domain\Enums\ModoImportacion::cases())->first(fn($m) => $m->value === $modo)?->label() ?? $modo }}</div>
                </div>
                <div class="rounded border border-ink-200 p-3">
                    <div class="text-[10px] uppercase text-ink-500">Campos sistema</div>
                    <div class="mt-1 font-semibold text-ink-900">{{ collect($columnas)->filter(fn($c) => $c['accion'] === 'mapear_sistema')->count() }}</div>
                </div>
                <div class="rounded border border-success-200 bg-success-50 p-3">
                    <div class="text-[10px] uppercase text-success-700">CP a crear</div>
                    <div class="mt-1 font-semibold text-success-900">{{ count($resultadoDryRun['camposPersonalizadosACrear'] ?? []) }}</div>
                </div>
                <div class="rounded border border-ink-200 p-3">
                    <div class="text-[10px] uppercase text-ink-500">CP reutilizados</div>
                    <div class="mt-1 font-semibold text-ink-900">{{ $resultadoDryRun['camposReutilizados'] ?? 0 }}</div>
                </div>
            </div>

            @if(isset($resultadoDryRun['camposPersonalizadosACrear']) && count($resultadoDryRun['camposPersonalizadosACrear']) > 0)
                <details class="rounded border border-ink-200 bg-ink-50 p-3 text-xs">
                    <summary class="cursor-pointer font-medium text-ink-700">Campos personalizados a crear ({{ count($resultadoDryRun['camposPersonalizadosACrear']) }})</summary>
                    <ul class="mt-2 space-y-1">
                        @foreach($resultadoDryRun['camposPersonalizadosACrear'] as $cp)
                            <li class="font-mono text-ink-600">{{ $cp }}</li>
                        @endforeach
                    </ul>
                </details>
            @endif

            <div>
                <label class="block text-xs font-medium text-ink-700 mb-1">Modo de importación</label>
                <select wire:model="modo" class="block w-full text-sm border-ink-300 rounded-md">
                    @foreach(\App\Modules\Importaciones\Domain\Enums\ModoImportacion::cases() as $m)
                        @if($m->esNuevo() || $m === \App\Modules\Importaciones\Domain\Enums\ModoImportacion::MERGE)
                            <option value="{{ $m->value }}">{{ $m->label() }} — {{ $m->descripcion() }}</option>
                        @endif
                    @endforeach
                </select>
            </div>

            @if(isset($resultadoDryRun['advertencias']) && count($resultadoDryRun['advertencias']) > 0)
                <div class="rounded border border-warning-200 bg-warning-50 p-3 text-xs text-warning-800">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach($resultadoDryRun['advertencias'] as $adv)
                            <li>{{ $adv }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="flex items-center justify-end gap-2">
                <button type="button" wire:click="cerrar"
                        class="px-3 py-1.5 text-xs text-ink-700 border border-ink-300 rounded hover:bg-ink-50">
                    Descartar
                </button>
                <button type="button" wire:click="ejecutar"
                        wire:confirm="¿Confirmar importación? Este proceso puede tardar varios minutos."
                        class="px-3 py-1.5 text-xs text-white bg-brand-600 rounded hover:bg-brand-700">
                    Ejecutar importación
                </button>
            </div>
            @error('columnas')<div class="text-xs text-danger-600">{{ $message }}</div>@enderror
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
                    <div class="text-[10px] uppercase text-success-700">Insertadas</div>
                    <div class="mt-1 font-semibold text-success-700">{{ $progreso->insertadas }}</div>
                </div>
                <div class="rounded border border-blue-200 bg-blue-50 p-3">
                    <div class="text-[10px] uppercase text-blue-700">Actualizadas</div>
                    <div class="mt-1 font-semibold text-blue-700">{{ $progreso->actualizadas }}</div>
                </div>
                <div class="rounded border border-warning-200 bg-warning-50 p-3">
                    <div class="text-[10px] uppercase text-warning-700">Duplicadas</div>
                    <div class="mt-1 font-semibold text-warning-700">{{ $progreso->duplicadas }}</div>
                </div>
                <div class="rounded border border-danger-200 bg-danger-50 p-3">
                    <div class="text-[10px] uppercase text-danger-700">Inválidas</div>
                    <div class="mt-1 font-semibold text-danger-700">{{ $progreso->invalidas }}</div>
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

            @if($resultadoDryRun !== null && ($resultadoDryRun['camposCreados'] ?? 0) > 0)
                <div class="text-xs text-success-700">
                    ✓ {{ $resultadoDryRun['camposCreados'] }} campos personalizados creados durante la importación.
                </div>
            @endif

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
                        <th class="px-3 py-2 text-right">Insertadas</th>
                        <th class="px-3 py-2 text-right">Actualizadas</th>
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
                            <td class="px-3 py-2 text-right font-mono text-success-700">{{ number_format((int) ($h->insertadas ?? 0)) }}</td>
                            <td class="px-3 py-2 text-right font-mono text-blue-700">{{ number_format((int) ($h->actualizadas ?? 0)) }}</td>
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
