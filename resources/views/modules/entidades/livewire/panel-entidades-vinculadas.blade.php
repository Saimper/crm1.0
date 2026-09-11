{{-- Registros de entidades configurables (§7.7) colgados de la cuenta o de la
     persona. Vive dentro de la pestaña «Cuenta» de la Vista de Trabajo: una
     sección por entidad, sin tarjeta propia. Sin entidades aplicables no pinta
     nada. --}}
<div class="flex flex-col gap-6 empty:hidden">
    @if(! empty($bloques))
        @if(session('entidades-registros-ok'))
            <div class="alert alert-success text-sm">{{ session('entidades-registros-ok') }}</div>
        @endif

        @foreach($bloques as $bloque)
            @php
                $entidad = $bloque['entidad'];
                $registros = $bloque['registros'];
            @endphp
            <section wire:key="entidad-{{ $entidad->id }}">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-base font-semibold text-ink">
                        {{ $entidad->nombre }} <span class="text-ink-400 font-medium">({{ $registros->count() }})</span>
                    </h3>
                    @if(auth()->user()->tienePermiso('entidades.crear', $proyectoId))
                        <button type="button" wire:click="abrirFormCrear({{ $entidad->id }})" class="btn-link -mr-2">
                            {{ __('entidades.add_record') }}
                        </button>
                    @endif
                </div>

                @if($formVisible && $entidadActivaId === (int) $entidad->id)
                    <div class="border border-ink-200 rounded-lg bg-surface-50 p-3.5 mb-3">
                        <h4 class="text-sm font-semibold text-ink mb-2">
                            {{ $registroEditandoId === null ? __('entidades.new_record') : __('entidades.edit_record') }}
                        </h4>
                        <form wire:submit.prevent="guardar" class="flex flex-col gap-2.5">
                            <div>
                                <label class="field-label">{{ __('entidades.label_title') }}</label>
                                <input type="text" wire:model="titulo" class="input @error('titulo') input-error @enderror"/>
                                @error('titulo')<div class="field-error">{{ $message }}</div>@enderror
                            </div>

                            <div class="grid grid-cols-[repeat(auto-fill,minmax(180px,1fr))] gap-2.5">
                                @foreach($camposForm as $campo)
                                    @php $codigo = (string) $campo->codigo; @endphp
                                    <div @class(['col-span-full' => in_array((string) $campo->tipo, ['texto_largo', 'seleccion_multiple'], true)])>
                                        <label class="field-label">
                                            {{ $campo->etiqueta }}
                                            @if($campo->obligatorio)<span class="text-danger-500">*</span>@endif
                                        </label>
                                        <x-cp.control :campo="$campo" model="valores.{{ $codigo }}" clase="input" />
                                    </div>
                                @endforeach
                            </div>

                            <div class="flex justify-end gap-1.5 mt-1">
                                <button type="button" wire:click="cerrarForm" class="btn btn-ghost btn-sm">{{ __('common.cancel') }}</button>
                                <button type="submit" class="btn btn-primary btn-sm">{{ __('common.save') }}</button>
                            </div>
                        </form>
                    </div>
                @endif

                @if($registros->isEmpty())
                    <div class="border border-dashed border-ink-200 rounded-lg p-3.5 text-center text-sm text-ink-500">
                        {{ __('entidades.empty_records') }}
                    </div>
                @else
                    <table class="table table-compact">
                        <thead>
                            <tr>
                                <th>{{ __('entidades.col_title') }}</th>
                                <th>{{ __('entidades.col_created') }}</th>
                                <th class="text-right">{{ __('entidades.col_actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($registros as $r)
                                <tr wire:key="registro-{{ $r->id }}">
                                    <td>{{ $r->titulo ?? '—' }}</td>
                                    <td class="font-mono text-sm text-ink-500">{{ hora_local($r->creado_en) }}</td>
                                    <td class="text-right whitespace-nowrap">
                                        @if(auth()->user()->tienePermiso('entidades.editar', $proyectoId))
                                            <button type="button" wire:click="abrirFormEditar({{ $entidad->id }}, {{ $r->id }})" class="btn-link">{{ __('common.edit') }}</button>
                                        @endif
                                        @if(auth()->user()->tienePermiso('entidades.eliminar', $proyectoId))
                                            <button type="button" wire:click="eliminar({{ $r->id }})"
                                                    wire:confirm="{{ __('entidades.confirm_delete_record') }}"
                                                    class="btn-link text-danger-600 hover:bg-danger-50">{{ __('common.delete') }}</button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </section>
        @endforeach
    @endif
</div>
