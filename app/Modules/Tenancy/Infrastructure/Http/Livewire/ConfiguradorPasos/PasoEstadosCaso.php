<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos;

use App\Modules\Tenancy\Infrastructure\Persistence\Models\ProyectoModel;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Paso 3 del wizard F36 — CRUD de estados_caso del proyecto.
 *
 * Schema sin `eliminada_en`: borrado físico tras chequeo de FK (casos).
 * Multi-tenancy: queries scoped por proyecto_id.
 */
final class PasoEstadosCaso extends Component
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
        'es_terminal' => false,
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
            'es_terminal' => false,
            'orden' => 0,
            'activo' => true,
        ];
        $this->formVisible = true;
        $this->resetErrorBag();
    }

    public function abrirFormEditar(int $id): void
    {
        $this->authorize('proyectos.configurar', (int) $this->proyecto->id);

        $row = DB::table('estados_caso')
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
            'es_terminal' => (bool) $row->es_terminal,
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
            'form.es_terminal' => ['required', 'boolean'],
            'form.orden' => ['required', 'integer', 'min:0'],
            'form.activo' => ['required', 'boolean'],
        ], [], [
            'form.codigo' => 'código',
            'form.nombre' => 'nombre',
            'form.descripcion' => 'descripción',
            'form.es_terminal' => 'estado terminal',
            'form.orden' => 'orden',
            'form.activo' => 'estado',
        ]);

        $proyectoId = (int) $this->proyecto->id;
        $codigo = strtoupper(trim((string) $this->form['codigo']));

        $duplicado = DB::table('estados_caso')
            ->where('proyecto_id', $proyectoId)
            ->where('codigo', $codigo)
            ->when($this->editandoId !== null, fn ($q) => $q->where('id', '!=', $this->editandoId))
            ->exists();

        if ($duplicado) {
            $this->addError('form.codigo', 'Ya existe otro estado con ese código en el proyecto.');

            return;
        }

        $payload = [
            'codigo' => $codigo,
            'nombre' => trim((string) $this->form['nombre']),
            'descripcion' => $this->descripcionOpcional(),
            'es_terminal' => (bool) $this->form['es_terminal'],
            'orden' => (int) $this->form['orden'],
            'activo' => (bool) $this->form['activo'],
            'actualizada_en' => Carbon::now(),
        ];

        if ($this->editandoId === null) {
            $payload['proyecto_id'] = $proyectoId;
            $payload['creada_en'] = Carbon::now();
            DB::table('estados_caso')->insert($payload);
        } else {
            DB::table('estados_caso')
                ->where('id', $this->editandoId)
                ->where('proyecto_id', $proyectoId)
                ->update($payload);
        }

        $this->cerrarForm();
        session()->flash('paso-estados-caso-ok', 'Estado guardado.');
        $this->dispatch('configuracion-paso-completado');
    }

    public function eliminar(int $id): void
    {
        $this->authorize('proyectos.configurar', (int) $this->proyecto->id);

        $proyectoId = (int) $this->proyecto->id;

        $existe = DB::table('estados_caso')
            ->where('id', $id)
            ->where('proyecto_id', $proyectoId)
            ->exists();

        if (! $existe) {
            return;
        }

        $tieneCasos = DB::table('casos')
            ->where('estado_caso_id', $id)
            ->exists();

        if ($tieneCasos) {
            session()->flash('paso-estados-caso-error', 'No se puede eliminar: hay casos en este estado.');

            return;
        }

        DB::table('estados_caso')
            ->where('id', $id)
            ->where('proyecto_id', $proyectoId)
            ->delete();

        session()->flash('paso-estados-caso-ok', 'Estado eliminado.');
        $this->dispatch('configuracion-paso-completado');
    }

    public function toggleActivo(int $id): void
    {
        $this->authorize('proyectos.configurar', (int) $this->proyecto->id);

        $actual = DB::table('estados_caso')
            ->where('id', $id)
            ->where('proyecto_id', (int) $this->proyecto->id)
            ->value('activo');

        if ($actual === null) {
            return;
        }

        DB::table('estados_caso')
            ->where('id', $id)
            ->where('proyecto_id', (int) $this->proyecto->id)
            ->update([
                'activo' => ! (bool) $actual,
                'actualizada_en' => Carbon::now(),
            ]);

        $this->dispatch('configuracion-paso-completado');
    }

    public function render(): View
    {
        $proyectoId = (int) $this->proyecto->id;
        $busqueda = trim($this->busqueda);

        $query = DB::table('estados_caso')
            ->where('proyecto_id', $proyectoId);

        if ($busqueda !== '') {
            $like = '%'.$busqueda.'%';
            $query->where(function ($q) use ($like): void {
                $q->where('codigo', 'like', $like)
                    ->orWhere('nombre', 'like', $like);
            });
        }

        $estados = $query
            ->orderBy('orden')
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre', 'descripcion', 'es_terminal', 'orden', 'activo']);

        return view('livewire.tenancy.configurador-pasos.paso-estados-caso', [
            'estados' => $estados,
        ]);
    }

    private function descripcionOpcional(): ?string
    {
        $valor = trim((string) ($this->form['descripcion'] ?? ''));

        return $valor === '' ? null : $valor;
    }
}
