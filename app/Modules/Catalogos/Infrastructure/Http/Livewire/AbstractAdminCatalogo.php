<?php

declare(strict_types=1);

namespace App\Modules\Catalogos\Infrastructure\Http\Livewire;

use App\Support\Codigo\GeneradorCodigo;
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
        $codigoInput = trim((string) ($this->form['codigo'] ?? ''));
        $nombre = (string) ($this->form['nombre'] ?? '');

        $codigoBase = $codigoInput === ''
            ? GeneradorCodigo::derivar($nombre, $this->maxLenCodigo())
            : GeneradorCodigo::normalizar($codigoInput, $this->maxLenCodigo());

        $codigoFinal = GeneradorCodigo::resolverConflicto(
            $codigoBase,
            function (string $candidato) use ($proyectoId): bool {
                $q = DB::table($this->tabla())
                    ->where('proyecto_id', $proyectoId)
                    ->where('codigo', $candidato);
                if ($this->editandoId !== null) {
                    $q->where('id', '!=', $this->editandoId);
                }

                return $q->exists();
            },
            $this->maxLenCodigo(),
        );

        $this->form['codigo'] = $codigoFinal;

        $payload = array_merge($this->payloadDesdeForm(), ['proyecto_id' => $proyectoId]);

        if ($this->editandoId === null) {
            // Auto-asignar orden al crear: max+10. El admin no decide números.
            if (! isset($payload['orden']) || (int) $payload['orden'] === 0) {
                $maxOrden = (int) DB::table($this->tabla())
                    ->where('proyecto_id', $proyectoId)
                    ->max('orden');
                $payload['orden'] = $maxOrden + 10;
            }
            DB::table($this->tabla())->insert($payload);
        } else {
            unset($payload['orden']); // No se mueve por edición; usar subir/bajar.
            DB::table($this->tabla())->where('id', $this->editandoId)->update($payload);
        }

        $this->cerrarForm();
        session()->flash('admin-catalogo-ok', 'Registro guardado.');
    }

    /**
     * Reordena: sube el item un puesto (intercambia orden con el inmediato anterior).
     * Permite al admin reorganizar visualmente sin tocar números.
     */
    public function subir(int $id): void
    {
        $proyectoId = $this->proyectoActivoId();
        $row = DB::table($this->tabla())
            ->where('id', $id)->where('proyecto_id', $proyectoId)->first();
        if ($row === null) {
            return;
        }
        $vecino = DB::table($this->tabla())
            ->where('proyecto_id', $proyectoId)
            ->where('orden', '<', $row->orden)
            ->orderByDesc('orden')->orderByDesc('id')->first();
        if ($vecino === null) {
            return;
        }
        DB::transaction(function () use ($row, $vecino): void {
            DB::table($this->tabla())->where('id', $row->id)->update(['orden' => $vecino->orden]);
            DB::table($this->tabla())->where('id', $vecino->id)->update(['orden' => $row->orden]);
        });
    }

    /**
     * Reordena: baja el item un puesto (intercambia orden con el inmediato siguiente).
     */
    public function bajar(int $id): void
    {
        $proyectoId = $this->proyectoActivoId();
        $row = DB::table($this->tabla())
            ->where('id', $id)->where('proyecto_id', $proyectoId)->first();
        if ($row === null) {
            return;
        }
        $vecino = DB::table($this->tabla())
            ->where('proyecto_id', $proyectoId)
            ->where('orden', '>', $row->orden)
            ->orderBy('orden')->orderBy('id')->first();
        if ($vecino === null) {
            return;
        }
        DB::transaction(function () use ($row, $vecino): void {
            DB::table($this->tabla())->where('id', $row->id)->update(['orden' => $vecino->orden]);
            DB::table($this->tabla())->where('id', $vecino->id)->update(['orden' => $row->orden]);
        });
    }

    /**
     * Long máximo del código en la tabla. Override en subclases que usan max:80.
     */
    protected function maxLenCodigo(): int
    {
        return 50;
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
