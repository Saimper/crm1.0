<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\CatalogosTipo;

use App\Modules\Tenancy\Infrastructure\Persistence\Models\ProyectoModel;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use stdClass;

/**
 * Base abstracta de los 10 sub-Livewires de catálogos tipo-específicos del Paso 7.
 *
 * Cada subclase declara: tabla(), columnasListado(), formInicial(), rulesEspecificas(),
 * labelsEspecificas(), construirPayload(), formDesdeRow(), dependenciasFk(), viewSlug().
 * Toda la lógica de orquestación (autorize, dispatch, validación de unicidad, render
 * del listado, drawer + delete + toggle) vive aquí — el patrón es idéntico para los 10.
 *
 * Multi-tenancy: queries scoped por proyecto_id en la base. Las subclases nunca
 * la sobrescriben.
 */
abstract class CatalogoTipoBase extends Component
{
    public ProyectoModel $proyecto;

    public string $busqueda = '';

    public bool $formVisible = false;

    public ?int $editandoId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(ProyectoModel $proyecto): void
    {
        $this->authorize('proyectos.configurar', (int) $proyecto->id);
        $this->proyecto = $proyecto;
        $this->form = $this->formInicial();
    }

    public function abrirFormCrear(): void
    {
        $this->authorize('proyectos.configurar', (int) $this->proyecto->id);
        $this->editandoId = null;
        $this->form = $this->formInicial();
        $this->formVisible = true;
        $this->resetErrorBag();
    }

    public function abrirFormEditar(int $id): void
    {
        $this->authorize('proyectos.configurar', (int) $this->proyecto->id);

        $row = DB::table($this->tabla())
            ->where('id', $id)
            ->where('proyecto_id', (int) $this->proyecto->id)
            ->first();

        if ($row === null) {
            return;
        }

        $this->editandoId = $id;
        $this->form = $this->formDesdeRow($row);
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

        $rules = array_merge([
            'form.codigo' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]{2,50}$/'],
            'form.nombre' => ['required', 'string', 'max:200'],
            'form.orden' => ['required', 'integer', 'min:0'],
            'form.activo' => ['required', 'boolean'],
        ], $this->rulesEspecificas());

        $labels = array_merge([
            'form.codigo' => 'código',
            'form.nombre' => 'nombre',
            'form.orden' => 'orden',
            'form.activo' => 'estado',
        ], $this->labelsEspecificas());

        $this->validate($rules, [], $labels);

        $proyectoId = (int) $this->proyecto->id;
        $codigo = strtoupper(trim((string) ($this->form['codigo'] ?? '')));

        $duplicado = DB::table($this->tabla())
            ->where('proyecto_id', $proyectoId)
            ->where('codigo', $codigo)
            ->when($this->editandoId !== null, fn ($q) => $q->where('id', '!=', $this->editandoId))
            ->exists();

        if ($duplicado) {
            $this->addError('form.codigo', 'Ya existe otro registro con ese código en el proyecto.');

            return;
        }

        if (! $this->validarNegocio($proyectoId)) {
            return;
        }

        $payload = array_merge(
            $this->construirPayload(),
            [
                'codigo' => $codigo,
                'nombre' => trim((string) ($this->form['nombre'] ?? '')),
                'orden' => (int) ($this->form['orden'] ?? 0),
                'activo' => (bool) ($this->form['activo'] ?? false),
                'actualizada_en' => Carbon::now(),
            ],
        );

        if ($this->editandoId === null) {
            $payload['proyecto_id'] = $proyectoId;
            $payload['creada_en'] = Carbon::now();
            DB::table($this->tabla())->insert($payload);
        } else {
            DB::table($this->tabla())
                ->where('id', $this->editandoId)
                ->where('proyecto_id', $proyectoId)
                ->update($payload);
        }

        $this->cerrarForm();
        session()->flash($this->mensajeFlashClave().'-ok', 'Registro guardado.');
        $this->dispatch('configuracion-paso-completado');
    }

    public function eliminar(int $id): void
    {
        $this->authorize('proyectos.configurar', (int) $this->proyecto->id);

        $proyectoId = (int) $this->proyecto->id;

        $existe = DB::table($this->tabla())
            ->where('id', $id)
            ->where('proyecto_id', $proyectoId)
            ->exists();

        if (! $existe) {
            return;
        }

        foreach ($this->dependenciasFk() as $dep) {
            $tieneRefs = DB::table($dep['tabla'])
                ->where($dep['columna'], $id)
                ->exists();

            if ($tieneRefs) {
                session()->flash(
                    $this->mensajeFlashClave().'-error',
                    'No se puede eliminar: hay registros operativos que dependen de este catálogo.',
                );

                return;
            }
        }

        DB::table($this->tabla())
            ->where('id', $id)
            ->where('proyecto_id', $proyectoId)
            ->delete();

        $this->cerrarForm();
        session()->flash($this->mensajeFlashClave().'-ok', 'Registro eliminado.');
        $this->dispatch('configuracion-paso-completado');
    }

    public function toggleActivo(int $id): void
    {
        $this->authorize('proyectos.configurar', (int) $this->proyecto->id);

        $proyectoId = (int) $this->proyecto->id;

        $actual = DB::table($this->tabla())
            ->where('id', $id)
            ->where('proyecto_id', $proyectoId)
            ->value('activo');

        if ($actual === null) {
            return;
        }

        DB::table($this->tabla())
            ->where('id', $id)
            ->where('proyecto_id', $proyectoId)
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

        $query = DB::table($this->tabla())
            ->where('proyecto_id', $proyectoId);

        if ($busqueda !== '') {
            $like = '%'.$busqueda.'%';
            $query->where(function ($q) use ($like): void {
                $q->where('codigo', 'like', $like)
                    ->orWhere('nombre', 'like', $like);
            });
        }

        $rows = $query
            ->orderBy('orden')
            ->orderBy('codigo')
            ->get($this->columnasListado());

        return view('livewire.tenancy.configurador-pasos.catalogos-tipo.'.$this->viewSlug(), [
            'rows' => $rows,
        ]);
    }

    abstract protected function tabla(): string;

    /**
     * @return array<string, mixed>
     */
    abstract protected function formInicial(): array;

    /**
     * @return array<string, mixed>
     */
    abstract protected function formDesdeRow(stdClass $row): array;

    /**
     * @return array<string, list<mixed>>
     */
    abstract protected function rulesEspecificas(): array;

    /**
     * @return array<string, string>
     */
    abstract protected function labelsEspecificas(): array;

    /**
     * Payload de columnas específicas del catálogo (sin codigo/nombre/orden/activo,
     * que la base ya completa). Devolver el array tal cual va a INSERT/UPDATE.
     *
     * @return array<string, mixed>
     */
    abstract protected function construirPayload(): array;

    /**
     * Dependencias FK: cada entry bloquea el borrado si hay filas con esa columna = id.
     *
     * @return list<array{tabla: string, columna: string}>
     */
    abstract protected function dependenciasFk(): array;

    /**
     * @return list<string>
     */
    abstract protected function columnasListado(): array;

    abstract protected function viewSlug(): string;

    /**
     * Hook opcional para validaciones de negocio extra (UNIQUE compuesto, etc.).
     * Devolver false aborta el guardado (los errores ya fueron seteados via addError).
     */
    protected function validarNegocio(int $proyectoId): bool
    {
        return true;
    }

    abstract protected function mensajeFlashClave(): string;
}
