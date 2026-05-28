<div class="space-y-6" @if($progreso !== null && $progreso->enCurso() && $progreso->estado->value === 'procesando') wire:poll.2s @endif>
    @if(session('importacion-ok'))
        <div class="rounded border border-success-200 bg-success-50 px-3 py-2 text-sm text-success-800">
            {{ session('importacion-ok') }}
        </div>
    @endif

    <section class="rounded-lg border border-ink-200 bg-white p-6 space-y-4">
        <div>
            <h3 class="text-sm font-semibold uppercase tracking-wider text-ink-700">{{ __('importaciones.import_cases_title', ['tipo' => $tipoOperacion]) }}</h3>
            <p class="text-xs text-ink-500 mt-1">
                {{ __('importaciones.import_cases_subtitle', ['tipo' => $tipoOperacion]) }}
            </p>
            <p class="text-xs text-ink-500 mt-1">
                <strong>{{ __('importaciones.required_columns') }}</strong> <code>{{ implode(', ', $columnasEsperadas) }}</code>
            </p>
        </div>

        @if($importacionId === null)
            <form wire:submit.prevent="guardarArchivo" class="space-y-3">
                <div>
                    <label class="block text-xs font-medium text-ink-700">{{ __('importaciones.csv_file_label') }}</label>
                    <input type="file" wire:model="archivo" accept=".csv,text/csv"
                           class="mt-1 block w-full text-sm text-ink-700"/>
                    @error('archivo')<div class="text-xs text-danger-600 mt-0.5">{{ $message }}</div>@enderror
                </div>

                <div>
                    <label class="block text-xs font-medium text-ink-700 mb-1">{{ __('importaciones.import_mode_label') }}</label>
                    <select wire:model="modo" class="mt-1 block w-full text-sm border-ink-300 rounded-md">
                        <option value="merge">{{ __('importaciones.mode_merge_casos') }}</option>
                        <option value="skip_duplicados">{{ __('importaciones.mode_skip_casos') }}</option>
                        <option value="overwrite">{{ __('importaciones.mode_overwrite_casos') }}</option>
                    </select>
                </div>

                <div>
                    <button type="submit"
                            class="inline-flex items-center px-4 py-2 bg-brand-600 text-white text-sm font-medium rounded-md hover:bg-brand-700">
                        {{ __('importaciones.upload_and_validate') }}
                    </button>
                </div>
            </form>
        @else
            @php $estadoActual = $progreso?->estado->value ?? '—'; @endphp

            <div class="space-y-3">
                <div class="grid grid-cols-1 sm:grid-cols-6 gap-3 text-sm">
                    <div class="rounded border border-ink-200 p-3">
                        <div class="text-[10px] uppercase text-ink-500">{{ __('importaciones.col_file') }}</div>
                        <div class="mt-1 font-medium text-ink-900 truncate">{{ $importacionActual->nombre_archivo ?? '—' }}</div>
                    </div>
                    <div class="rounded border border-ink-200 p-3">
                        <div class="text-[10px] uppercase text-ink-500">{{ __('importaciones.label_total') }}</div>
                        <div class="mt-1 font-semibold text-ink-900">{{ $progreso?->totalFilas ?? 0 }}</div>
                    </div>
                    <div class="rounded border border-success-200 bg-success-50 p-3">
                        <div class="text-[10px] uppercase text-success-700">{{ __('importaciones.label_processed') }}</div>
                        <div class="mt-1 font-semibold text-success-700">{{ $progreso?->procesadas ?? 0 }}</div>
                    </div>
                    <div class="rounded border border-brand-200 bg-brand-50 p-3">
                        <div class="text-[10px] uppercase text-brand-700">{{ __('importaciones.label_valid') }}</div>
                        <div class="mt-1 font-semibold text-brand-900">{{ $progreso?->validas ?? 0 }}</div>
                    </div>
                    <div class="rounded border border-warning-200 bg-warning-50 p-3">
                        <div class="text-[10px] uppercase text-warning-700">{{ __('importaciones.label_duplicated') }}</div>
                        <div class="mt-1 font-semibold text-warning-700">{{ $progreso?->duplicadas ?? 0 }}</div>
                    </div>
                    <div class="rounded border border-danger-200 bg-danger-50 p-3">
                        <div class="text-[10px] uppercase text-danger-700">{{ __('importaciones.label_invalid') }}</div>
                        <div class="mt-1 font-semibold text-danger-700">{{ $progreso?->invalidas ?? 0 }}</div>
                    </div>
                </div>

                @if($progreso !== null)
                    <div class="space-y-1">
                        <div class="flex items-center justify-between text-xs text-ink-700">
                            <span>{{ __('importaciones.label_status') }}: <code>{{ $estadoActual }}</code> · {{ __('importaciones.label_mode') }}: <code>{{ $progreso->modo->value }}</code></span>
                            <span class="font-mono">{{ $progreso->porcentaje() }}%</span>
                        </div>
                        <div class="w-full bg-ink-200 rounded h-2 overflow-hidden">
                            <div class="bg-brand-600 h-2 transition-all duration-500" style="width: {{ $progreso->porcentaje() }}%"></div>
                        </div>
                        @if($progreso->errorGlobal)
                            <div class="text-xs text-danger-700 mt-1">{{ __('importaciones.error_prefix', ['message' => $progreso->errorGlobal]) }}</div>
                        @endif
                    </div>
                @endif

                @if($estadoActual === 'preparada')
                    <div>
                        <label class="block text-xs font-medium text-ink-700 mb-1">{{ __('importaciones.change_mode_label') }}</label>
                        <select wire:model="modo" class="block w-full text-sm border-ink-300 rounded-md">
                            <option value="merge">merge</option>
                            <option value="skip_duplicados">skip_duplicados</option>
                            <option value="overwrite">overwrite</option>
                        </select>

                    </div>
                @endif

                <div class="flex items-center gap-2">
                    <label class="text-xs text-ink-600">{{ __('importaciones.filter_rows_label') }}</label>
                    <select wire:model.live="filtroFilas" class="text-xs border-ink-300 rounded">
                        <option value="todas">{{ __('importaciones.filter_all') }}</option>
                        <option value="pendiente">{{ __('importaciones.filter_pending') }}</option>
                        <option value="procesada">{{ __('importaciones.filter_processed') }}</option>
                        <option value="duplicada">{{ __('importaciones.filter_duplicated') }}</option>
                        <option value="invalida">{{ __('importaciones.filter_invalid') }}</option>
                        <option value="omitida">{{ __('importaciones.filter_omitted') }}</option>
                    </select>
                </div>

                <div class="rounded-md border border-ink-200 overflow-x-auto max-h-96 overflow-y-auto">
                    <table class="min-w-full divide-y divide-ink-200 text-xs">
                        <thead class="bg-ink-50 uppercase tracking-wider text-ink-600 sticky top-0">
                            <tr>
                                <th class="px-2 py-2 text-left">{{ __('importaciones.col_row_num') }}</th>
                                <th class="px-2 py-2 text-left">{{ __('importaciones.col_row_status') }}</th>
                                <th class="px-2 py-2 text-left">{{ __('importaciones.col_identification') }}</th>
                                <th class="px-2 py-2 text-left">{{ __('importaciones.col_code') }}</th>
                                <th class="px-2 py-2 text-left">{{ __('importaciones.col_detail') }}</th>
                                <th class="px-2 py-2 text-left">{{ __('importaciones.col_row_action') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100">
                            @foreach($preview as $f)
                                @php
                                    $p = is_array($f->payload) ? $f->payload : json_decode($f->payload, true);
                                    $codigoCaso = $p['numero_prestamo']
                                        ?? $p['codigo_ticket']
                                        ?? $p['codigo_lead']
                                        ?? $p['codigo_servicio']
                                        ?? '';
                                    $badge = match ($f->estado) {
                                        'procesada' => 'bg-success-50 text-success-700',
                                        'duplicada' => 'bg-warning-50 text-warning-700',
                                        'invalida'  => 'bg-danger-50 text-danger-700',
                                        'omitida'   => 'bg-ink-200 text-ink-700',
                                        default     => 'bg-ink-100 text-ink-700',
                                    };
                                    $detalle = $f->mensaje_error ?: $f->razon_omision;
                                @endphp
                                @php $resuelto = $casosResueltos[$f->numero_fila] ?? null; @endphp
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
                                    <td class="px-2 py-1 text-ink-700">{{ $detalle }}</td>
                                    <td class="px-2 py-1">
                                        @if($resuelto !== null)
                                            <a href="{{ route('proyectos.trabajo', ['proyecto_id' => app('tenancy.proyecto_activo')->id, 'persona' => $resuelto['persona_public_id'], 'caso' => $resuelto['caso_public_id']]) }}"
                                               wire:navigate class="text-brand-600 hover:underline">{{ __('importaciones.link_view') }}</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex items-center justify-end gap-2">
                    @if($estadoActual === 'preparada')
                        <button type="button" wire:click="cerrar"
                                class="px-3 py-1.5 text-xs text-ink-700 border border-ink-300 rounded hover:bg-ink-50">
                            {{ __('importaciones.btn_discard') }}
                        </button>
                        <button type="button" wire:click="confirmar"
                                wire:confirm="{{ __('importaciones.confirm_process', ['mode' => $modo]) }}"
                                class="px-3 py-1.5 text-xs text-white bg-brand-600 rounded hover:bg-brand-700">
                            {{ __('importaciones.btn_process_background') }}
                        </button>
                    @elseif($estadoActual === 'procesando')
                        <button type="button" wire:click="cancelar"
                                wire:confirm="{{ __('importaciones.confirm_cancel') }}"
                                class="px-3 py-1.5 text-xs text-white bg-danger-600 rounded hover:bg-danger-700">
                            {{ __('importaciones.btn_cancel_active') }}
                        </button>
                    @elseif(in_array($estadoActual, ['completada', 'fallida', 'cancelada']))
                        <button type="button" wire:click="cerrar"
                                class="px-3 py-1.5 text-xs text-white bg-success-600 rounded hover:bg-success-700">
                            {{ __('importaciones.btn_close') }}
                        </button>
                    @endif
                </div>
            </div>
        @endif
    </section>

    <section class="rounded-lg border border-ink-200 bg-white overflow-hidden">
        <div class="px-4 py-3 border-b border-ink-200 bg-ink-50 text-xs font-semibold uppercase tracking-wider text-ink-600">
            {{ __('importaciones.history_cases_title', ['count' => $historial->count()]) }}
        </div>
        @if($historial->isEmpty())
            <div class="p-6 text-sm text-ink-500 text-center">{{ __('importaciones.history_cases_empty') }}</div>
        @else
            <table class="min-w-full divide-y divide-ink-200 text-sm">
                <thead class="bg-ink-50 text-xs uppercase tracking-wider text-ink-600">
                    <tr>
                        <th class="px-3 py-2 text-left">{{ __('importaciones.col_date') }}</th>
                        <th class="px-3 py-2 text-left">{{ __('importaciones.col_file') }}</th>
                        <th class="px-3 py-2 text-left">{{ __('importaciones.col_type') }}</th>
                        <th class="px-3 py-2 text-left">{{ __('importaciones.col_mode') }}</th>
                        <th class="px-3 py-2 text-left">{{ __('importaciones.col_user') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('importaciones.col_total') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('importaciones.col_processed') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('importaciones.col_duplicated') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('importaciones.col_invalid') }}</th>
                        <th class="px-3 py-2 text-left">{{ __('importaciones.col_status') }}</th>
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
                            <td class="px-3 py-2 text-right font-mono">{{ number_format($h->total_filas) }}</td>
                            <td class="px-3 py-2 text-right font-mono text-success-700">{{ number_format($h->procesadas) }}</td>
                            <td class="px-3 py-2 text-right font-mono text-warning-700">{{ number_format($h->duplicadas) }}</td>
                            <td class="px-3 py-2 text-right font-mono text-danger-700">{{ number_format($h->invalidas) }}</td>
                            <td class="px-3 py-2 text-xs"><code>{{ $h->estado }}</code></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>
</div>
