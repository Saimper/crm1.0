<?php

declare(strict_types=1);

namespace App\Modules\Catalogos\Infrastructure\Http\Livewire;

use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Clase base para Livewire de catálogos operativos por proyecto (scoped).
 * Centraliza: detección de proyecto activo, listado, alternar estado, abrir/cerrar form,
 * validación común de código único por proyecto. Las clases hijas definen tabla, campos y reglas.
 */
abstract class AbstractAdminCatalogo extends Component
{
    public bool $formVisible = false;

    public ?int $editandoId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(): void
    {
        $this->form = $this->formVacio();
    }

    abstract protected function tabla(): string;

    /** @return array<string, mixed> */
    abstract protected function formVacio(): array;

    /** @return array<string, list<string>> */
    abstract protected function reglasValidacion(): array;

    /**
     * @return array<string, mixed> Payload listo para insert/update con columnas reales de la tabla.
     */
    abstract protected function payloadDesdeForm(): array;

    /**
     * Transforma la fila de la tabla (objeto) a los valores del form para editar.
     *
     * @param object $row
     * @return array<string, mixed>
     */
    abstract protected function formDesdeFila(object $row): array;

    public function abrirFormCrear(): void
    {
        $this->editandoId = null;
        $this->form = $this->formVacio();
        $this->formVisible = true;
        $this->resetErrorBag();
    }

    public function abrirFormEditar(int $id): void
    {
        $row = DB::table($this->tabla())->where('id', $id)->first();
        if ($row === null || (int) $row->proyecto_id !== $this->proyectoActivoId()) {
            return;
        }

        $this->editandoId = $id;
        $this->form = $this->formDesdeFila($row);
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
        $this->validate($this->reglasValidacion());

        $proyectoId = $this->proyectoActivoId();
        $codigo = (string) ($this->form['codigo'] ?? '');

        $query = DB::table($this->tabla())
            ->where('proyecto_id', $proyectoId)
            ->where('codigo', $codigo);
        if ($this->editandoId !== null) {
            $query->where('id', '!=', $this->editandoId);
        }
        if ($query->exists()) {
            $this->addError('form.codigo', 'Ya existe un registro con ese código en el proyecto.');
            return;
        }

        $payload = array_merge($this->payloadDesdeForm(), ['proyecto_id' => $proyectoId]);

        if ($this->editandoId === null) {
            DB::table($this->tabla())->insert($payload);
        } else {
            DB::table($this->tabla())->where('id', $this->editandoId)->update($payload);
        }

        $this->cerrarForm();
        session()->flash('admin-catalogo-ok', 'Registro guardado.');
    }

    public function desactivar(int $id): void
    {
        DB::table($this->tabla())
            ->where('id', $id)
            ->where('proyecto_id', $this->proyectoActivoId())
            ->update(['activo' => false]);
        session()->flash('admin-catalogo-ok', 'Registro desactivado.');
    }

    public function activar(int $id): void
    {
        DB::table($this->tabla())
            ->where('id', $id)
            ->where('proyecto_id', $this->proyectoActivoId())
            ->update(['activo' => true]);
        session()->flash('admin-catalogo-ok', 'Registro activado.');
    }

    protected function proyectoActivoId(): int
    {
        return (int) app('tenancy.proyecto_activo')->id;
    }
}
