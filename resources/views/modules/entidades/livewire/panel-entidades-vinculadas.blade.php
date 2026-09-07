<div>
    @if(empty($bloques))
        {{-- Sin entidades aplicables: panel oculto. --}}
    @else
        <div style="margin-top:12px;">
            @if(session('entidades-registros-ok'))
                <div class="alert alert-success" style="margin-bottom:8px;">
                    {{ session('entidades-registros-ok') }}
                </div>
            @endif

            @foreach($bloques as $bloque)
                @php
                    $entidad = $bloque['entidad'];
                    $registros = $bloque['registros'];
                @endphp
                <x-ui.card :title="$entidad->nombre" style="margin-bottom:10px;">
                    @if(auth()->user()->tienePermiso('entidades.crear', $proyectoId))
                        <div style="margin-bottom:8px;text-align:right;">
                            <button type="button"
                                    wire:click="abrirFormCrear({{ $entidad->id }})"
                                    class="btn btn-ghost btn-sm">
                                {{ __('entidades.add_record') }}
                            </button>
                        </div>
                    @endif

                    @if($formVisible && $entidadActivaId === (int) $entidad->id)
                        <div class="card card-pad" style="margin-bottom:10px;background:var(--bg-subtle);">
                            <h4 style="font-size:12px;font-weight:600;margin-bottom:8px;">
                                {{ $registroEditandoId === null ? __('entidades.new_record') : __('entidades.edit_record') }}
                            </h4>
                            <form wire:submit.prevent="guardar" class="space-y-2">
                                <div>
                                    <label class="field-label">{{ __('entidades.label_title') }}</label>
                                    <input type="text" wire:model="titulo"
                                           class="input @error('titulo') input-error @enderror"/>
                                    @error('titulo')<div class="field-error">{{ $message }}</div>@enderror
                                </div>

                                @foreach($camposForm as $campo)
                                    @php $codigo = (string) $campo->codigo; @endphp
                                    <div>
                                        <label class="field-label">
                                            {{ $campo->etiqueta }}
                                            @if($campo->obligatorio)<span style="color:var(--danger);">*</span>@endif
                                        </label>
                                        <x-cp.control :campo="$campo" model="valores.{{ $codigo }}" clase="input" />
                                    </div>
                                @endforeach

                                <div style="display:flex;justify-content:flex-end;gap:6px;margin-top:8px;">
                                    <button type="button" wire:click="cerrarForm" class="btn btn-ghost btn-sm">
                                        {{ __('common.cancel') }}
                                    </button>
                                    <button type="submit" class="btn btn-primary btn-sm">{{ __('common.save') }}</button>
                                </div>
                            </form>
                        </div>
                    @endif

                    @if($registros->isEmpty())
                        <div style="padding:8px;font-size:12px;color:var(--text-tertiary);">
                            {{ __('entidades.empty_records') }}
                        </div>
                    @else
                        <table class="table table-compact" style="font-size:12px;">
                            <thead>
                                <tr>
                                    <th>{{ __('entidades.col_title') }}</th>
                                    <th>{{ __('entidades.col_created') }}</th>
                                    <th style="text-align:right;">{{ __('entidades.col_actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($registros as $r)
                                    <tr>
                                        <td>{{ $r->titulo ?? '—' }}</td>
                                        <td style="color:var(--text-tertiary);">
                                            {{ \Illuminate\Support\Carbon::parse($r->creado_en)->format('d/m/Y H:i') }}
                                        </td>
                                        <td style="text-align:right;">
                                            @if(auth()->user()->tienePermiso('entidades.editar', $proyectoId))
                                                <button type="button"
                                                        wire:click="abrirFormEditar({{ $entidad->id }}, {{ $r->id }})"
                                                        class="btn btn-ghost btn-xs">{{ __('common.edit') }}</button>
                                            @endif
                                            @if(auth()->user()->tienePermiso('entidades.eliminar', $proyectoId))
                                                <button type="button"
                                                        wire:click="eliminar({{ $r->id }})"
                                                        wire:confirm="{{ __('entidades.confirm_delete_record') }}"
                                                        class="btn btn-ghost btn-xs"
                                                        style="color:var(--danger);">{{ __('common.delete') }}</button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </x-ui.card>
            @endforeach
        </div>
    @endif
</div>
