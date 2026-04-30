<div class="space-y-6">
    @if(session('importacion-ok'))
        <div class="rounded border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
            {{ session('importacion-ok') }}
        </div>
    @endif

    <section class="rounded-lg border border-gray-200 bg-white p-6 space-y-4">
        <div>
            <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-700">Importar {{ $tipoOperacion }}</h3>
            <p class="text-xs text-gray-500 mt-1">
                Tipo de operación del proyecto: <code>{{ $tipoOperacion }}</code>. Solo pueden importarse casos de este tipo.
            </p>
            <p class="text-xs text-gray-500 mt-1">
                <strong>Columnas obligatorias:</strong>
                <code>{{ implode(', ', $columnasEsperadas) }}</code>
            </p>
            <p class="text-xs text-gray-500 mt-1">
                La persona debe estar importada previamente en el proyecto (usa la pestaña Personas si hace falta).
                CSV hasta 4 MB. Primero validamos, luego confirmas.
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
                    <button type="submit"
                            class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700">
                        Subir y validar
                    </button>
                </div>
            </form>
        @else
            <div class="space-y-3">
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 text-sm">
                    <div class="rounded border border-gray-200 p-3">
                        <div class="text-[10px] uppercase text-gray-500">Archivo</div>
                        <div class="mt-1 font-medium text-gray-900">{{ $importacionActual->nombre_archivo ?? '—' }}</div>
                    </div>
                    <div class="rounded border border-gray-200 p-3">
                        <div class="text-[10px] uppercase text-gray-500">Total filas</div>
                        <div class="mt-1 font-semibold text-gray-900">{{ $importacionActual->total_filas ?? 0 }}</div>
                    </div>
                    <div class="rounded border border-emerald-200 bg-emerald-50 p-3">
                        <div class="text-[10px] uppercase text-emerald-700">Válidas</div>
                        <div class="mt-1 font-semibold text-emerald-900">{{ $importacionActual->filas_ok ?? 0 }}</div>
                    </div>
                    <div class="rounded border border-red-200 bg-red-50 p-3">
                        <div class="text-[10px] uppercase text-red-700">Con error</div>
                        <div class="mt-1 font-semibold text-red-900">{{ $importacionActual->filas_error ?? 0 }}</div>
                    </div>
                </div>

                <div class="text-xs text-gray-600">
                    Estado: <code>{{ $importacionActual->estado ?? '—' }}</code>
                    @if(($importacionActual->estado ?? null) === 'completada')
                        · Importadas reales: <span class="font-semibold text-emerald-700">{{ $importacionActual->filas_importadas ?? 0 }}</span>
                    @endif
                </div>

                <div class="rounded-md border border-gray-200 overflow-x-auto max-h-96 overflow-y-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-xs">
                        <thead class="bg-gray-50 uppercase tracking-wider text-gray-600 sticky top-0">
                            <tr>
                                <th class="px-2 py-2 text-left">#</th>
                                <th class="px-2 py-2 text-left">Estado</th>
                                <th class="px-2 py-2 text-left">Identificación</th>
                                <th class="px-2 py-2 text-left">Código / Préstamo</th>
                                <th class="px-2 py-2 text-left">Error</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($preview as $f)
                                @php
                                    $p = is_array($f->payload) ? $f->payload : json_decode($f->payload, true);
                                    $codigoCaso = $p['numero_prestamo']
                                        ?? $p['codigo_ticket']
                                        ?? $p['codigo_lead']
                                        ?? $p['codigo_servicio']
                                        ?? '';
                                    $badge = match ($f->estado) {
                                        'valida'    => 'bg-emerald-100 text-emerald-800',
                                        'importada' => 'bg-emerald-200 text-emerald-900',
                                        'invalida'  => 'bg-red-100 text-red-800',
                                        'omitida'   => 'bg-amber-100 text-amber-800',
                                        default     => 'bg-gray-100 text-gray-700',
                                    };
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
                                    <td class="px-2 py-1 font-mono">{{ $codigoCaso }}</td>
                                    <td class="px-2 py-1 text-red-700">{{ $f->mensaje_error }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex items-center justify-end gap-2">
                    <button type="button" wire:click="cancelar"
                            class="px-3 py-1.5 text-xs text-gray-700 border border-gray-300 rounded hover:bg-gray-50">
                        Cancelar
                    </button>
                    @if(($importacionActual->estado ?? null) === 'validada')
                        <button type="button" wire:click="confirmar"
                                wire:confirm="¿Confirmar importación? Se crearán los casos válidos."
                                class="px-3 py-1.5 text-xs text-white bg-blue-600 rounded hover:bg-blue-700">
                            Confirmar importación
                        </button>
                    @elseif(($importacionActual->estado ?? null) === 'completada')
                        <button type="button" wire:click="cancelar"
                                class="px-3 py-1.5 text-xs text-white bg-emerald-600 rounded hover:bg-emerald-700">
                            Finalizar
                        </button>
                    @endif
                </div>
            </div>
        @endif
    </section>

    <section class="rounded-lg border border-gray-200 bg-white overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200 bg-gray-50 text-xs font-semibold uppercase tracking-wider text-gray-600">
            Historial de importaciones de casos ({{ $historial->count() }})
        </div>
        @if($historial->isEmpty())
            <div class="p-6 text-sm text-gray-500 text-center">Aún no hay importaciones de casos en este proyecto.</div>
        @else
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-600">
                    <tr>
                        <th class="px-3 py-2 text-left">Fecha</th>
                        <th class="px-3 py-2 text-left">Archivo</th>
                        <th class="px-3 py-2 text-left">Tipo</th>
                        <th class="px-3 py-2 text-left">Usuario</th>
                        <th class="px-3 py-2 text-right">Total</th>
                        <th class="px-3 py-2 text-right">Importadas</th>
                        <th class="px-3 py-2 text-right">Errores</th>
                        <th class="px-3 py-2 text-left">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($historial as $h)
                        <tr>
                            <td class="px-3 py-2 text-xs">{{ \Illuminate\Support\Carbon::parse($h->creada_en)->format('d/m/Y H:i') }}</td>
                            <td class="px-3 py-2 text-xs">{{ $h->nombre_archivo }}</td>
                            <td class="px-3 py-2 text-xs"><code>{{ $h->tipo_entidad }}</code></td>
                            <td class="px-3 py-2 text-xs">{{ $h->usuario_nombre ?? '—' }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format($h->total_filas) }}</td>
                            <td class="px-3 py-2 text-right font-mono text-emerald-700">{{ number_format($h->filas_importadas) }}</td>
                            <td class="px-3 py-2 text-right font-mono text-red-700">{{ number_format($h->filas_error) }}</td>
                            <td class="px-3 py-2 text-xs"><code>{{ $h->estado }}</code></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>
</div>
