<div class="space-y-6">
    @if(session('entidades-ok'))
        <div class="rounded border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
            {{ session('entidades-ok') }}
        </div>
    @endif

    <section class="rounded-lg border border-gray-200 bg-white p-4">
        <div class="flex items-end justify-between gap-3">
            <div class="flex-1 max-w-sm">
                <label class="block text-xs font-medium text-gray-700">Proyecto</label>
                <select wire:model.live="proyectoSeleccionadoId" class="mt-1 block w-full border-gray-300 rounded-md text-sm">
                    @foreach($proyectos as $p)
                        <option value="{{ $p->id }}">{{ $p->codigo }} — {{ $p->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <button type="button" wire:click="abrirFormCrear"
                    class="px-3 py-1.5 text-xs text-white bg-indigo-600 rounded hover:bg-indigo-700">
                Nueva entidad
            </button>
        </div>
    </section>

    @if($formVisible)
        <section class="rounded-lg border border-indigo-200 bg-indigo-50 p-4">
            <h4 class="text-sm font-semibold text-indigo-900 mb-3">
                {{ $entidadEditandoId === null ? 'Crear entidad' : 'Editar entidad' }}
            </h4>
            <form wire:submit.prevent="guardarEntidad" class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
                <div>
                    <label class="block text-xs font-medium text-gray-700">Código</label>
                    <input type="text" wire:model="formCodigo"
                           class="mt-1 block w-full border-gray-300 rounded-md text-sm font-mono uppercase"
                           placeholder="POLIZAS"/>
                    @error('formCodigo')<div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">Nombre</label>
                    <input type="text" wire:model="formNombre"
                           class="mt-1 block w-full border-gray-300 rounded-md text-sm"
                           placeholder="Pólizas de seguro"/>
                    @error('formNombre')<div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">Ícono (opcional)</label>
                    <input type="text" wire:model="formIcono"
                           class="mt-1 block w-full border-gray-300 rounded-md text-sm"
                           placeholder="document"/>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">Relación con núcleo</label>
                    <select wire:model="formRelacion" class="mt-1 block w-full border-gray-300 rounded-md text-sm">
                        <option value="ninguna">Ninguna (entidad suelta)</option>
                        <option value="caso">Caso (1 caso → N registros)</option>
                        <option value="persona">Persona (1 persona → N registros)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">Restringir a cartera (opcional)</label>
                    <select wire:model="formCarteraId" class="mt-1 block w-full border-gray-300 rounded-md text-sm">
                        <option value="">Todas las carteras</option>
                        @foreach($carterasDelProyecto as $c)
                            <option value="{{ $c->id }}">{{ $c->codigo }} — {{ $c->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">Activa</label>
                    <select wire:model="formActivo" class="mt-1 block w-full border-gray-300 rounded-md text-sm">
                        <option value="1">Sí</option>
                        <option value="0">No</option>
                    </select>
                </div>
                <div class="md:col-span-3">
                    <label class="block text-xs font-medium text-gray-700">Descripción (opcional)</label>
                    <textarea wire:model="formDescripcion" rows="2"
                              class="mt-1 block w-full border-gray-300 rounded-md text-sm"></textarea>
                </div>
                <div class="md:col-span-3 flex items-center justify-end gap-2">
                    <button type="button" wire:click="cerrarForm"
                            class="px-3 py-1.5 text-xs text-gray-700 border border-gray-300 rounded hover:bg-gray-50">Cancelar</button>
                    <button type="submit"
                            class="px-3 py-1.5 text-xs text-white bg-indigo-600 rounded hover:bg-indigo-700">Guardar</button>
                </div>
            </form>
        </section>
    @endif

    <section class="rounded-lg border border-gray-200 bg-white overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200 bg-gray-50 text-xs font-semibold uppercase tracking-wider text-gray-600">
            Entidades ({{ $entidades->count() }})
        </div>
        @if($entidades->isEmpty())
            <div class="p-6 text-sm text-gray-500 text-center">Aún no hay entidades configurables en este proyecto.</div>
        @else
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-600">
                    <tr>
                        <th class="px-3 py-2 text-left">Código</th>
                        <th class="px-3 py-2 text-left">Nombre</th>
                        <th class="px-3 py-2 text-left">Relación</th>
                        <th class="px-3 py-2 text-left">Cartera</th>
                        <th class="px-3 py-2 text-center">Estado</th>
                        <th class="px-3 py-2 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($entidades as $e)
                        <tr>
                            <td class="px-3 py-2 font-mono">{{ $e->codigo }}</td>
                            <td class="px-3 py-2">{{ $e->nombre }}</td>
                            <td class="px-3 py-2 text-xs">{{ $e->relacion_con }}</td>
                            <td class="px-3 py-2 text-xs">{{ $e->cartera_nombre ?? '— todas —' }}</td>
                            <td class="px-3 py-2 text-center">
                                @if($e->activo)
                                    <span class="inline-block rounded bg-emerald-100 text-emerald-800 px-1.5 py-0.5 text-[10px]">Activa</span>
                                @else
                                    <span class="inline-block rounded bg-gray-200 text-gray-700 px-1.5 py-0.5 text-[10px]">Inactiva</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right text-xs space-x-3">
                                <button type="button" wire:click="abrirCamposDe({{ $e->id }})"
                                        class="text-indigo-700 hover:underline">Campos</button>
                                <button type="button" wire:click="abrirFormEditar({{ $e->id }})"
                                        class="text-gray-700 hover:underline">Editar</button>
                                <button type="button" wire:click="eliminarEntidad({{ $e->id }})"
                                        wire:confirm="¿Desactivar la entidad?"
                                        class="text-red-600 hover:underline">Desactivar</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    @if($entidadConCamposAbiertosId !== null)
        <section class="rounded-lg border border-indigo-200 bg-white p-4 space-y-3">
            <div class="flex items-center justify-between">
                <h4 class="text-sm font-semibold text-gray-800">Campos de la entidad</h4>
                <div class="space-x-2">
                    @if(! $formCampoVisible)
                        <button type="button" wire:click="abrirFormCampoCrear"
                                class="px-3 py-1.5 text-xs text-white bg-indigo-600 rounded hover:bg-indigo-700">
                            Nuevo campo
                        </button>
                    @endif
                    <button type="button" wire:click="cerrarCampos"
                            class="text-xs text-gray-500 hover:text-gray-700">× Cerrar</button>
                </div>
            </div>

            @if($formCampoVisible)
                <form wire:submit.prevent="guardarCampo" class="grid grid-cols-1 md:grid-cols-5 gap-2 text-sm bg-indigo-50 p-3 rounded">
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Código</label>
                        <input type="text" wire:model="formCampoCodigo"
                               class="mt-1 block w-full border-gray-300 rounded-md text-sm font-mono"
                               placeholder="numero_poliza"/>
                        @error('formCampoCodigo')<div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Etiqueta</label>
                        <input type="text" wire:model="formCampoEtiqueta"
                               class="mt-1 block w-full border-gray-300 rounded-md text-sm"/>
                        @error('formCampoEtiqueta')<div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Tipo</label>
                        <select wire:model="formCampoTipo" class="mt-1 block w-full border-gray-300 rounded-md text-sm">
                            <option value="texto_corto">Texto corto</option>
                            <option value="texto_largo">Texto largo</option>
                            <option value="numero_entero">Número entero</option>
                            <option value="numero_decimal">Número decimal</option>
                            <option value="fecha">Fecha</option>
                            <option value="fecha_hora">Fecha y hora</option>
                            <option value="booleano">Sí/No</option>
                            <option value="moneda">Moneda</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Orden</label>
                        <input type="number" wire:model="formCampoOrden" min="0"
                               class="mt-1 block w-full border-gray-300 rounded-md text-sm"/>
                    </div>
                    <div class="flex items-end gap-2">
                        <label class="flex items-center gap-1 text-xs">
                            <input type="checkbox" wire:model="formCampoObligatorio" class="rounded border-gray-300"/>
                            Obligatorio
                        </label>
                    </div>
                    <div class="md:col-span-5 flex items-center justify-end gap-2">
                        <button type="button" wire:click="cerrarFormCampo"
                                class="px-3 py-1.5 text-xs text-gray-700 border border-gray-300 rounded hover:bg-gray-50">Cancelar</button>
                        <button type="submit"
                                class="px-3 py-1.5 text-xs text-white bg-indigo-600 rounded hover:bg-indigo-700">Guardar campo</button>
                    </div>
                </form>
            @endif

            @if($campos->isEmpty())
                <div class="text-sm text-gray-500 text-center py-4">Esta entidad aún no tiene campos.</div>
            @else
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-600">
                        <tr>
                            <th class="px-3 py-2 text-left">Código</th>
                            <th class="px-3 py-2 text-left">Etiqueta</th>
                            <th class="px-3 py-2 text-left">Tipo</th>
                            <th class="px-3 py-2 text-center">Obligatorio</th>
                            <th class="px-3 py-2 text-right">Orden</th>
                            <th class="px-3 py-2 text-center">Activo</th>
                            <th class="px-3 py-2 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($campos as $c)
                            <tr>
                                <td class="px-3 py-2 font-mono">{{ $c->codigo }}</td>
                                <td class="px-3 py-2">{{ $c->etiqueta }}</td>
                                <td class="px-3 py-2 text-xs font-mono">{{ $c->tipo }}</td>
                                <td class="px-3 py-2 text-center">{{ $c->obligatorio ? 'Sí' : 'No' }}</td>
                                <td class="px-3 py-2 text-right font-mono">{{ $c->orden }}</td>
                                <td class="px-3 py-2 text-center">{{ $c->activo ? 'Sí' : 'No' }}</td>
                                <td class="px-3 py-2 text-right text-xs space-x-2">
                                    <button type="button" wire:click="abrirFormCampoEditar({{ $c->id }})"
                                            class="text-gray-700 hover:underline">Editar</button>
                                    @if($c->activo)
                                        <button type="button" wire:click="desactivarCampo({{ $c->id }})"
                                                class="text-red-600 hover:underline">Desactivar</button>
                                    @else
                                        <button type="button" wire:click="activarCampo({{ $c->id }})"
                                                class="text-emerald-700 hover:underline">Activar</button>
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
