<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos;

use App\Modules\Tenancy\Infrastructure\Persistence\Models\ProyectoModel;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;
use stdClass;

/**
 * Paso 5 del wizard F36 — CRUD de resultados del proyecto.
 *
 * Schema: codigo, nombre, descripcion, activo, orden, es_contacto_efectivo,
 * requiere_compromiso, requiere_causa. SIN tipo_gestion_id (acoplamiento operacional,
 * no FK física — CLAUDE.md §7.2). Subresultados NO existen como tabla; se omite
 * la sub-feature por completo (auditoría P0 riesgo #1).
 */
final class PasoResultados extends Component
{
    public ProyectoModel $proyecto;

    public string $busqueda = '';

    public bool $formVisible = false;

    public ?int $editandoId = null;

    /** @var array<string, mixed> */
    public array $form = [
        'codigo' => '',
        'nombre' => '',
        'descripcion' => '',
        'es_contacto_efectivo' => false,
        'requiere_compromiso' => false,
        'estado_caso_cierre_id' => null,
        'requiere_causa' => false,
        'orden' => 0,
        'activo' => true,
    ];

    public function mount(ProyectoModel $proyecto): void
    {
        $this->authorize('proyectos.configurar', (int) $proyecto->id);
        $this->proyecto = $proyecto;
    }

    public function abrirFormCrear(): void
    {
        $this->authorize('proyectos.configurar', (int) $this->proyecto->id);
        $this->editandoId = null;
        $this->form = [
            'codigo' => '',
            'nombre' => '',
            'descripcion' => '',
            'es_contacto_efectivo' => false,
            'requiere_compromiso' => false,
            'estado_caso_cierre_id' => null,
            'requiere_causa' => false,
            'orden' => 0,
            'activo' => true,
        ];
        $this->formVisible = true;
        $this->resetErrorBag();
    }

    public function abrirFormEditar(int $id): void
    {
        $this->authorize('proyectos.configurar', (int) $this->proyecto->id);

        $row = DB::table('resultados')
            ->where('id', $id)
            ->where('proyecto_id', (int) $this->proyecto->id)
            ->first();

        if ($row === null) {
            return;
        }

        $this->editandoId = $id;
        $this->form = [
            'codigo' => (string) $row->codigo,
            'nombre' => (string) $row->nombre,
            'descripcion' => (string) ($row->descripcion ?? ''),
            'es_contacto_efectivo' => (bool) $row->es_contacto_efectivo,
            'requiere_compromiso' => (bool) $row->requiere_compromiso,
            'estado_caso_cierre_id' => $row->estado_caso_cierre_id === null ? null : (int) $row->estado_caso_cierre_id,
            'requiere_causa' => (bool) $row->requiere_causa,
            'orden' => (int) $row->orden,
            'activo' => (bool) $row->activo,
        ];
        $this->formVisible = true;
        $this->resetErrorBag();
    }

    public function cerrarForm(): void
    {
        $this->formVisible = false;
        $this->editandoId = null;
        $this->resetErrorBag();
    }

    public function guardar(): void
    {
        $this->authorize('proyectos.configurar', (int) $this->proyecto->id);

        $this->validate([
            'form.codigo' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]{2,50}$/'],
            'form.nombre' => ['required', 'string', 'max:150'],
            'form.descripcion' => ['nullable', 'string', 'max:500'],
            'form.es_contacto_efectivo' => ['required', 'boolean'],
            'form.requiere_compromiso' => ['required', 'boolean'],
            // Debe ser un estado TERMINAL del propio proyecto: cerrar hacia un
            // estado intermedio dejaria el caso marcado como cerrado pero en un
            // estado que la operacion sigue considerando vivo.
            'form.estado_caso_cierre_id' => ['nullable', 'integer', Rule::exists('estados_caso', 'id')
                ->where('proyecto_id', (int) $this->proyecto->id)
                ->where('es_terminal', true)
                ->where('activo', true)],
            'form.requiere_causa' => ['required', 'boolean'],
            'form.orden' => ['required', 'integer', 'min:0'],
            'form.activo' => ['required', 'boolean'],
        ], [], [
            'form.codigo' => 'código',
            'form.nombre' => 'nombre',
            'form.descripcion' => 'descripción',
            'form.es_contacto_efectivo' => 'contacto efectivo',
            'form.requiere_compromiso' => 'requiere compromiso',
            'form.estado_caso_cierre_id' => 'estado de cierre',
            'form.requiere_causa' => 'requiere causa',
            'form.orden' => 'orden',
            'form.activo' => 'estado',
        ]);

        $proyectoId = (int) $this->proyecto->id;
        $codigo = strtoupper(trim((string) $this->form['codigo']));

        $duplicado = DB::table('resultados')
            ->where('proyecto_id', $proyectoId)
            ->where('codigo', $codigo)
            ->when($this->editandoId !== null, fn ($q) => $q->where('id', '!=', $this->editandoId))
            ->exists();

        if ($duplicado) {
            $this->addError('form.codigo', 'Ya existe otro resultado con ese código en el proyecto.');

            return;
        }

        $payload = [
            'codigo' => $codigo,
            'nombre' => trim((string) $this->form['nombre']),
            'descripcion' => $this->descripcionOpcional(),
            'es_contacto_efectivo' => (bool) $this->form['es_contacto_efectivo'],
            'requiere_compromiso' => (bool) $this->form['requiere_compromiso'],
            'estado_caso_cierre_id' => $this->form['estado_caso_cierre_id'] === null || $this->form['estado_caso_cierre_id'] === ''
                ? null
                : (int) $this->form['estado_caso_cierre_id'],
            'requiere_causa' => (bool) $this->form['requiere_causa'],
            'orden' => (int) $this->form['orden'],
            'activo' => (bool) $this->form['activo'],
            'actualizada_en' => Carbon::now(),
        ];

        if ($this->editandoId === null) {
            $payload['proyecto_id'] = $proyectoId;
            $payload['creada_en'] = Carbon::now();
            DB::table('resultados')->insert($payload);
        } else {
            DB::table('resultados')
                ->where('id', $this->editandoId)
                ->where('proyecto_id', $proyectoId)
                ->update($payload);
        }

        $this->cerrarForm();
        session()->flash('paso-resultados-ok', 'Resultado guardado.');
        $this->dispatch('configuracion-paso-completado');
    }

    public function eliminar(int $id): void
    {
        $this->authorize('proyectos.configurar', (int) $this->proyecto->id);

        $proyectoId = (int) $this->proyecto->id;

        $existe = DB::table('resultados')
            ->where('id', $id)
            ->where('proyecto_id', $proyectoId)
            ->exists();

        if (! $existe) {
            return;
        }

        $tieneGestiones = DB::table('gestiones')
            ->where('resultado_id', $id)
            ->exists();

        if ($tieneGestiones) {
            session()->flash('paso-resultados-error', 'No se puede eliminar: hay gestiones registradas con este resultado.');

            return;
        }

        DB::table('resultados')
            ->where('id', $id)
            ->where('proyecto_id', $proyectoId)
            ->delete();

        session()->flash('paso-resultados-ok', 'Resultado eliminado.');
        $this->dispatch('configuracion-paso-completado');
    }

    public function toggleActivo(int $id): void
    {
        $this->authorize('proyectos.configurar', (int) $this->proyecto->id);

        $actual = DB::table('resultados')
            ->where('id', $id)
            ->where('proyecto_id', (int) $this->proyecto->id)
            ->value('activo');

        if ($actual === null) {
            return;
        }

        DB::table('resultados')
            ->where('id', $id)
            ->where('proyecto_id', (int) $this->proyecto->id)
            ->update([
                'activo' => ! (bool) $actual,
                'actualizada_en' => Carbon::now(),
            ]);

        $this->dispatch('configuracion-paso-completado');
    }

    // -----------------------------------------------------------------
    // Plantillas de nota
    // -----------------------------------------------------------------

    /** @var array<string, mixed> */
    public array $plantilla = ['etiqueta' => '', 'texto' => '', 'resultado_id' => null];

    /**
     * Crea una frase hecha para el campo de notas.
     *
     * Con resultado, sale sólo bajo ese resultado, que es cuando ahorra
     * escribir de verdad. Sin él, sale siempre.
     */
    public function crearPlantilla(): void
    {
        $this->authorize('proyectos.configurar', (int) $this->proyecto->id);

        $proyectoId = (int) $this->proyecto->id;

        $this->validate([
            'plantilla.etiqueta' => ['required', 'string', 'max:60'],
            'plantilla.texto' => ['required', 'string', 'max:500'],
            'plantilla.resultado_id' => ['nullable', 'integer', Rule::exists('resultados', 'id')
                ->where('proyecto_id', $proyectoId)],
        ], [], [
            'plantilla.etiqueta' => 'etiqueta',
            'plantilla.texto' => 'texto',
            'plantilla.resultado_id' => 'resultado',
        ]);

        $siguiente = (int) DB::table('plantillas_nota')->where('proyecto_id', $proyectoId)->max('orden');

        DB::table('plantillas_nota')->insert([
            'proyecto_id' => $proyectoId,
            'resultado_id' => $this->plantilla['resultado_id'] === null || $this->plantilla['resultado_id'] === ''
                ? null
                : (int) $this->plantilla['resultado_id'],
            'etiqueta' => trim((string) $this->plantilla['etiqueta']),
            'texto' => trim((string) $this->plantilla['texto']),
            'activo' => true,
            'orden' => $siguiente + 10,
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        $this->plantilla = ['etiqueta' => '', 'texto' => '', 'resultado_id' => null];
    }

    public function eliminarPlantilla(int $id): void
    {
        $this->authorize('proyectos.configurar', (int) $this->proyecto->id);

        DB::table('plantillas_nota')
            ->where('id', $id)
            ->where('proyecto_id', (int) $this->proyecto->id)
            ->delete();
    }

    // -----------------------------------------------------------------
    // Matriz tipo de gestión × resultado
    // -----------------------------------------------------------------

    /**
     * Marca o desmarca que un tipo de gestión admita un resultado.
     *
     * Es una lista blanca de pares, de la misma clase que las banderas
     * `requiere_compromiso` y `requiere_causa` que ya son configurables por
     * proyecto: no ejecuta lógica de usuario. La línea a no cruzar es la
     * siguiente —«si el resultado es X entonces el campo Y es obligatorio»—,
     * que sería un motor de reglas y §13.14 lo prohíbe.
     */
    public function alternarCombinacion(int $tipoGestionId, int $resultadoId): void
    {
        $this->authorize('proyectos.configurar', (int) $this->proyecto->id);

        $proyectoId = (int) $this->proyecto->id;

        // Las dos puntas tienen que ser de este proyecto: los ids llegan del
        // payload y `proyecto_id` en la tabla no sirve de nada si no se mira.
        $tipoValido = DB::table('tipos_gestion')->where('id', $tipoGestionId)->where('proyecto_id', $proyectoId)->exists();
        $resultadoValido = DB::table('resultados')->where('id', $resultadoId)->where('proyecto_id', $proyectoId)->exists();

        if (! $tipoValido || ! $resultadoValido) {
            abort(403, 'Ese tipo de gestión o ese resultado no son de este proyecto.');
        }

        $existente = DB::table('resultado_tipo_gestion')
            ->where('proyecto_id', $proyectoId)
            ->where('tipo_gestion_id', $tipoGestionId)
            ->where('resultado_id', $resultadoId)
            ->first(['id']);

        if ($existente !== null) {
            DB::table('resultado_tipo_gestion')->where('id', $existente->id)->delete();

            return;
        }

        DB::table('resultado_tipo_gestion')->insert([
            'proyecto_id' => $proyectoId,
            'tipo_gestion_id' => $tipoGestionId,
            'resultado_id' => $resultadoId,
            'orden' => 0,
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);
    }

    public function render(): View
    {
        $proyectoId = (int) $this->proyecto->id;
        $busqueda = trim($this->busqueda);

        $query = DB::table('resultados')
            ->where('proyecto_id', $proyectoId);

        if ($busqueda !== '') {
            $like = '%'.$busqueda.'%';
            $query->where(function ($q) use ($like): void {
                $q->where('codigo', 'like', $like)
                    ->orWhere('nombre', 'like', $like);
            });
        }

        $resultados = $query
            ->orderBy('orden')
            ->orderBy('codigo')
            ->get([
                'id', 'codigo', 'nombre', 'descripcion',
                'es_contacto_efectivo', 'requiere_compromiso', 'requiere_causa', 'estado_caso_cierre_id',
                'orden', 'activo',
            ]);

        // Estados en los que un resultado puede dar el caso por cerrado. Si el
        // proyecto no tiene ninguno terminal, el selector no se ofrece.
        $estadosTerminales = DB::table('estados_caso')
            ->where('proyecto_id', $proyectoId)
            ->where('es_terminal', true)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre']);

        return view('livewire.tenancy.configurador-pasos.paso-resultados', [
            'plantillas' => DB::table('plantillas_nota as p')
                ->leftJoin('resultados as r', 'r.id', '=', 'p.resultado_id')
                ->where('p.proyecto_id', $proyectoId)
                ->orderBy('p.orden')->orderBy('p.id')
                ->get(['p.id', 'p.etiqueta', 'p.texto', 'r.nombre as resultado_nombre']),
            'tiposGestion' => DB::table('tipos_gestion')
                ->where('proyecto_id', $proyectoId)->where('activo', true)
                ->orderBy('orden')->orderBy('id')->get(['id', 'codigo', 'nombre']),
            'combinaciones' => DB::table('resultado_tipo_gestion')
                ->where('proyecto_id', $proyectoId)
                ->get(['tipo_gestion_id', 'resultado_id'])
                ->map(fn (stdClass $c): string => $c->tipo_gestion_id.'-'.$c->resultado_id)
                ->flip(),
            'resultados' => $resultados,
            'estadosTerminales' => $estadosTerminales,
        ]);
    }

    private function descripcionOpcional(): ?string
    {
        $valor = trim((string) ($this->form['descripcion'] ?? ''));

        return $valor === '' ? null : $valor;
    }
}
