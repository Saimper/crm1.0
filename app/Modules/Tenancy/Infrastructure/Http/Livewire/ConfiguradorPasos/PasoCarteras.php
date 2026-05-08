<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos;

use App\Modules\Tenancy\Domain\ValueObjects\CodigoCartera;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\ProyectoModel;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Component;

/**
 * Paso 2 del wizard F36 — CRUD de carteras del proyecto.
 *
 * Mutación directa (DB::table) siguiendo el patrón AdminCarterasProyecto.
 * Multi-tenancy: todas las queries scoped por proyecto_id (excepción explícita
 * solo en chequeos de unicidad por (proyecto_id, codigo)).
 * Reusa CodigoCartera VO para regla de unicidad de formato.
 */
final class PasoCarteras extends Component
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
        'activo' => true,
    ];

    public function mount(ProyectoModel $proyecto): void
    {
        $this->autorizar();
        $this->proyecto = $proyecto;
    }

    public function abrirFormCrear(): void
    {
        $this->autorizar();
        $this->editandoId = null;
        $this->form = ['codigo' => '', 'nombre' => '', 'descripcion' => '', 'activo' => true];
        $this->formVisible = true;
        $this->resetErrorBag();
    }

    public function abrirFormEditar(int $id): void
    {
        $this->autorizar();

        $row = DB::table('carteras')
            ->where('id', $id)
            ->where('proyecto_id', (int) $this->proyecto->id)
            ->whereNull('eliminada_en')
            ->first();

        if ($row === null) {
            return;
        }

        $this->editandoId = $id;
        $this->form = [
            'codigo' => (string) $row->codigo,
            'nombre' => (string) $row->nombre,
            'descripcion' => (string) ($row->descripcion ?? ''),
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

    public function guardarCartera(): void
    {
        $this->autorizar();

        $this->validate([
            'form.codigo' => ['required', 'string', 'max:80'],
            'form.nombre' => ['required', 'string', 'max:200'],
            'form.descripcion' => ['nullable', 'string', 'max:500'],
            'form.activo' => ['required', 'boolean'],
        ], [], [
            'form.codigo' => 'código',
            'form.nombre' => 'nombre',
            'form.descripcion' => 'descripción',
            'form.activo' => 'estado',
        ]);

        try {
            $codigoVO = new CodigoCartera((string) $this->form['codigo']);
        } catch (InvalidArgumentException $e) {
            $this->addError('form.codigo', $e->getMessage());

            return;
        }

        $codigoNormalizado = $codigoVO->asString();
        $proyectoId = (int) $this->proyecto->id;

        $duplicadoQuery = DB::table('carteras')
            ->where('proyecto_id', $proyectoId)
            ->where('codigo', $codigoNormalizado)
            ->whereNull('eliminada_en');

        if ($this->editandoId !== null) {
            $duplicadoQuery->where('id', '!=', $this->editandoId);
        }

        if ($duplicadoQuery->exists()) {
            $this->addError('form.codigo', 'Ya existe otra cartera con ese código en el proyecto.');

            return;
        }

        $payload = [
            'codigo' => $codigoNormalizado,
            'nombre' => trim((string) $this->form['nombre']),
            'descripcion' => $this->descripcionOpcional(),
            'activo' => (bool) $this->form['activo'],
            'actualizada_en' => Carbon::now(),
        ];

        if ($this->editandoId === null) {
            $payload['public_id'] = (string) Str::ulid();
            $payload['proyecto_id'] = $proyectoId;
            $payload['creada_en'] = Carbon::now();

            DB::table('carteras')->insert($payload);
        } else {
            DB::table('carteras')
                ->where('id', $this->editandoId)
                ->where('proyecto_id', $proyectoId)
                ->update($payload);
        }

        $this->cerrarForm();
        session()->flash('paso-carteras-ok', 'Cartera guardada.');
        $this->dispatch('configuracion-paso-completado');
    }

    public function eliminarCartera(int $id): void
    {
        $this->autorizar();

        $proyectoId = (int) $this->proyecto->id;

        $existe = DB::table('carteras')
            ->where('id', $id)
            ->where('proyecto_id', $proyectoId)
            ->whereNull('eliminada_en')
            ->exists();

        if (! $existe) {
            return;
        }

        $tieneCasos = DB::table('casos')
            ->where('cartera_id', $id)
            ->exists();

        if ($tieneCasos) {
            session()->flash('paso-carteras-error', 'No se puede eliminar la cartera porque tiene casos asociados.');

            return;
        }

        DB::table('carteras')
            ->where('id', $id)
            ->where('proyecto_id', $proyectoId)
            ->update(['eliminada_en' => Carbon::now()]);

        session()->flash('paso-carteras-ok', 'Cartera eliminada.');
        $this->dispatch('configuracion-paso-completado');
    }

    public function toggleActivo(int $id): void
    {
        $this->autorizar();

        $proyectoId = (int) $this->proyecto->id;

        $estadoActual = DB::table('carteras')
            ->where('id', $id)
            ->where('proyecto_id', $proyectoId)
            ->whereNull('eliminada_en')
            ->value('activo');

        if ($estadoActual === null) {
            return;
        }

        DB::table('carteras')
            ->where('id', $id)
            ->where('proyecto_id', $proyectoId)
            ->update([
                'activo' => ! (bool) $estadoActual,
                'actualizada_en' => Carbon::now(),
            ]);

        session()->flash('paso-carteras-ok', 'Estado actualizado.');
        $this->dispatch('configuracion-paso-completado');
    }

    public function render(): View
    {
        $proyectoId = (int) $this->proyecto->id;
        $busqueda = trim($this->busqueda);

        $query = DB::table('carteras as c')
            ->leftJoin('casos as cs', function ($join): void {
                $join->on('cs.cartera_id', '=', 'c.id')->whereNull('cs.eliminada_en');
            })
            ->where('c.proyecto_id', $proyectoId)
            ->whereNull('c.eliminada_en');

        if ($busqueda !== '') {
            $like = '%'.$busqueda.'%';
            $query->where(function ($q) use ($like): void {
                $q->where('c.codigo', 'like', $like)
                    ->orWhere('c.nombre', 'like', $like);
            });
        }

        $carteras = $query
            ->select([
                'c.id', 'c.codigo', 'c.nombre', 'c.descripcion', 'c.activo',
                DB::raw('count(cs.id) as total_casos'),
            ])
            ->groupBy('c.id', 'c.codigo', 'c.nombre', 'c.descripcion', 'c.activo')
            ->orderBy('c.codigo')
            ->get();

        return view('livewire.tenancy.configurador-pasos.paso-carteras', [
            'carteras' => $carteras,
        ]);
    }

    private function descripcionOpcional(): ?string
    {
        $valor = trim((string) ($this->form['descripcion'] ?? ''));

        return $valor === '' ? null : $valor;
    }

    /**
     * Defensa en profundidad (patrón F23).
     */
    private function autorizar(): void
    {
        $user = auth()->user();
        if ($user === null) {
            abort(403);
        }
        if ($user->esAdminGlobal()) {
            return;
        }
        if (! $user->tienePermiso('proyectos.configurar')) {
            abort(403, 'No autorizado para configurar el proyecto.');
        }
    }
}
