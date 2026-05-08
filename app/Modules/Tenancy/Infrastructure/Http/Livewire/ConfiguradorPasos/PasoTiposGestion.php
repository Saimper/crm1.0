<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos;

use App\Modules\Tenancy\Infrastructure\Persistence\Models\ProyectoModel;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Paso 4 del wizard F36 — CRUD de tipos_gestion del proyecto.
 *
 * Schema sin `descripcion` ni `eliminada_en`. No existe FK física entre
 * `resultados` y `tipos_gestion` (CLAUDE.md §7.2 — acoplamiento operacional),
 * por lo que el chequeo de dependencia con resultados se omite por ausencia
 * de columna; basta con bloquear si hay gestiones que referencian el tipo.
 */
final class PasoTiposGestion extends Component
{
    public ProyectoModel $proyecto;

    public string $busqueda = '';

    public bool $formVisible = false;

    public ?int $editandoId = null;

    /** @var array<string, mixed> */
    public array $form = [
        'codigo' => '',
        'nombre' => '',
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
        $this->form = ['codigo' => '', 'nombre' => '', 'orden' => 0, 'activo' => true];
        $this->formVisible = true;
        $this->resetErrorBag();
    }

    public function abrirFormEditar(int $id): void
    {
        $this->authorize('proyectos.configurar', (int) $this->proyecto->id);

        $row = DB::table('tipos_gestion')
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
            'form.orden' => ['required', 'integer', 'min:0'],
            'form.activo' => ['required', 'boolean'],
        ], [], [
            'form.codigo' => 'código',
            'form.nombre' => 'nombre',
            'form.orden' => 'orden',
            'form.activo' => 'estado',
        ]);

        $proyectoId = (int) $this->proyecto->id;
        $codigo = strtoupper(trim((string) $this->form['codigo']));

        $duplicado = DB::table('tipos_gestion')
            ->where('proyecto_id', $proyectoId)
            ->where('codigo', $codigo)
            ->when($this->editandoId !== null, fn ($q) => $q->where('id', '!=', $this->editandoId))
            ->exists();

        if ($duplicado) {
            $this->addError('form.codigo', 'Ya existe otro tipo de gestión con ese código en el proyecto.');

            return;
        }

        $payload = [
            'codigo' => $codigo,
            'nombre' => trim((string) $this->form['nombre']),
            'orden' => (int) $this->form['orden'],
            'activo' => (bool) $this->form['activo'],
            'actualizada_en' => Carbon::now(),
        ];

        if ($this->editandoId === null) {
            $payload['proyecto_id'] = $proyectoId;
            $payload['creada_en'] = Carbon::now();
            DB::table('tipos_gestion')->insert($payload);
        } else {
            DB::table('tipos_gestion')
                ->where('id', $this->editandoId)
                ->where('proyecto_id', $proyectoId)
                ->update($payload);
        }

        $this->cerrarForm();
        session()->flash('paso-tipos-gestion-ok', 'Tipo de gestión guardado.');
        $this->dispatch('configuracion-paso-completado');
    }

    public function eliminar(int $id): void
    {
        $this->authorize('proyectos.configurar', (int) $this->proyecto->id);

        $proyectoId = (int) $this->proyecto->id;

        $existe = DB::table('tipos_gestion')
            ->where('id', $id)
            ->where('proyecto_id', $proyectoId)
            ->exists();

        if (! $existe) {
            return;
        }

        $tieneGestiones = DB::table('gestiones')
            ->where('tipo_gestion_id', $id)
            ->exists();

        if ($tieneGestiones) {
            session()->flash('paso-tipos-gestion-error', 'No se puede eliminar: hay gestiones registradas con este tipo.');

            return;
        }

        DB::table('tipos_gestion')
            ->where('id', $id)
            ->where('proyecto_id', $proyectoId)
            ->delete();

        session()->flash('paso-tipos-gestion-ok', 'Tipo de gestión eliminado.');
        $this->dispatch('configuracion-paso-completado');
    }

    public function toggleActivo(int $id): void
    {
        $this->authorize('proyectos.configurar', (int) $this->proyecto->id);

        $actual = DB::table('tipos_gestion')
            ->where('id', $id)
            ->where('proyecto_id', (int) $this->proyecto->id)
            ->value('activo');

        if ($actual === null) {
            return;
        }

        DB::table('tipos_gestion')
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

        $query = DB::table('tipos_gestion')
            ->where('proyecto_id', $proyectoId);

        if ($busqueda !== '') {
            $like = '%'.$busqueda.'%';
            $query->where(function ($q) use ($like): void {
                $q->where('codigo', 'like', $like)
                    ->orWhere('nombre', 'like', $like);
            });
        }

        $tipos = $query
            ->orderBy('orden')
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre', 'orden', 'activo']);

        return view('livewire.tenancy.configurador-pasos.paso-tipos-gestion', [
            'tipos' => $tipos,
        ]);
    }
}
