<?php

declare(strict_types=1);

namespace App\Modules\Catalogos\Infrastructure\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

/**
 * CRUD de resultados del proyecto activo. Incluye banderas de dominio
 * (es_contacto_efectivo, requiere_compromiso, requiere_causa).
 */
final class AdminResultadosProyecto extends AbstractAdminCatalogo
{
    protected function tabla(): string
    {
        return 'resultados';
    }

    protected function formVacio(): array
    {
        return [
            'codigo' => '',
            'nombre' => '',
            'descripcion' => '',
            'es_contacto_efectivo' => false,
            'requiere_compromiso' => false,
            'requiere_causa' => false,
            'orden' => 100,
            'activo' => true,
        ];
    }

    protected function reglasValidacion(): array
    {
        return [
            'form.codigo' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9_]+$/'],
            'form.nombre' => ['required', 'string', 'max:150'],
            'form.descripcion' => ['nullable', 'string', 'max:500'],
            'form.es_contacto_efectivo' => ['boolean'],
            'form.requiere_compromiso' => ['boolean'],
            'form.requiere_causa' => ['boolean'],
            'form.orden' => ['integer', 'min:0'],
            'form.activo' => ['boolean'],
        ];
    }

    protected function payloadDesdeForm(): array
    {
        return [
            'codigo' => (string) $this->form['codigo'],
            'nombre' => (string) $this->form['nombre'],
            'descripcion' => ($this->form['descripcion'] ?? '') !== '' ? (string) $this->form['descripcion'] : null,
            'es_contacto_efectivo' => (bool) ($this->form['es_contacto_efectivo'] ?? false),
            'requiere_compromiso' => (bool) ($this->form['requiere_compromiso'] ?? false),
            'requiere_causa' => (bool) ($this->form['requiere_causa'] ?? false),
            'orden' => (int) ($this->form['orden'] ?? 100),
            'activo' => (bool) ($this->form['activo'] ?? true),
        ];
    }

    protected function formDesdeFila(object $row): array
    {
        return [
            'codigo' => (string) $row->codigo,
            'nombre' => (string) $row->nombre,
            'descripcion' => (string) ($row->descripcion ?? ''),
            'es_contacto_efectivo' => (bool) $row->es_contacto_efectivo,
            'requiere_compromiso' => (bool) $row->requiere_compromiso,
            'requiere_causa' => (bool) $row->requiere_causa,
            'orden' => (int) $row->orden,
            'activo' => (bool) $row->activo,
        ];
    }

    public function render(): View
    {
        $resultados = DB::table('resultados')
            ->where('proyecto_id', $this->proyectoActivoId())
            ->orderBy('orden')
            ->orderBy('codigo')
            ->get();

        return view('catalogos::livewire.admin-resultados', ['resultados' => $resultados]);
    }
}
