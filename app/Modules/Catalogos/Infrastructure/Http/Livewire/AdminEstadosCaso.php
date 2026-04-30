<?php

declare(strict_types=1);

namespace App\Modules\Catalogos\Infrastructure\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

/**
 * CRUD de estados_caso del proyecto. Incluye `es_terminal` para distinguir estados de cierre.
 * Protege contra desactivar un estado aún referenciado por casos activos (sería fuente de rotura).
 */
final class AdminEstadosCaso extends AbstractAdminCatalogo
{
    protected function tabla(): string
    {
        return 'estados_caso';
    }

    protected function formVacio(): array
    {
        return [
            'codigo' => '',
            'nombre' => '',
            'es_terminal' => false,
            'orden' => 100,
            'activo' => true,
        ];
    }

    protected function reglasValidacion(): array
    {
        return [
            'form.codigo' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9_]+$/'],
            'form.nombre' => ['required', 'string', 'max:150'],
            'form.es_terminal' => ['boolean'],
            'form.orden' => ['integer', 'min:0'],
            'form.activo' => ['boolean'],
        ];
    }

    protected function payloadDesdeForm(): array
    {
        return [
            'codigo' => (string) $this->form['codigo'],
            'nombre' => (string) $this->form['nombre'],
            'es_terminal' => (bool) ($this->form['es_terminal'] ?? false),
            'orden' => (int) ($this->form['orden'] ?? 100),
            'activo' => (bool) ($this->form['activo'] ?? true),
        ];
    }

    protected function formDesdeFila(object $row): array
    {
        return [
            'codigo' => (string) $row->codigo,
            'nombre' => (string) $row->nombre,
            'es_terminal' => (bool) $row->es_terminal,
            'orden' => (int) $row->orden,
            'activo' => (bool) $row->activo,
        ];
    }

    public function desactivar(int $id): void
    {
        $enUso = DB::table('casos')
            ->where('estado_caso_id', $id)
            ->where('proyecto_id', $this->proyectoActivoId())
            ->whereNull('eliminada_en')
            ->exists();

        if ($enUso) {
            session()->flash('admin-catalogo-error', 'No se puede desactivar: hay casos usando este estado.');

            return;
        }

        parent::desactivar($id);
    }

    public function render(): View
    {
        $items = DB::table('estados_caso')
            ->where('proyecto_id', $this->proyectoActivoId())
            ->orderBy('orden')
            ->orderBy('codigo')
            ->get();

        return view('catalogos::livewire.admin-estados-caso', ['items' => $items]);
    }
}
