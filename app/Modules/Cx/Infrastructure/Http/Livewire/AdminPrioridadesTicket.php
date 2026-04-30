<?php

declare(strict_types=1);

namespace App\Modules\Cx\Infrastructure\Http\Livewire;

use App\Modules\Catalogos\Infrastructure\Http\Livewire\AbstractAdminCatalogo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

final class AdminPrioridadesTicket extends AbstractAdminCatalogo
{
    protected function tabla(): string
    {
        return 'prioridades_ticket';
    }

    protected function formVacio(): array
    {
        return ['codigo' => '', 'nombre' => '', 'peso' => 100, 'orden' => 100, 'activo' => true];
    }

    protected function reglasValidacion(): array
    {
        return [
            'form.codigo' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9_]+$/'],
            'form.nombre' => ['required', 'string', 'max:150'],
            'form.peso' => ['integer', 'min:0'],
            'form.orden' => ['integer', 'min:0'],
            'form.activo' => ['boolean'],
        ];
    }

    protected function payloadDesdeForm(): array
    {
        return [
            'codigo' => (string) $this->form['codigo'],
            'nombre' => (string) $this->form['nombre'],
            'peso' => (int) ($this->form['peso'] ?? 100),
            'orden' => (int) ($this->form['orden'] ?? 100),
            'activo' => (bool) ($this->form['activo'] ?? true),
        ];
    }

    protected function formDesdeFila(object $row): array
    {
        return [
            'codigo' => (string) $row->codigo,
            'nombre' => (string) $row->nombre,
            'peso' => (int) $row->peso,
            'orden' => (int) $row->orden,
            'activo' => (bool) $row->activo,
        ];
    }

    public function render(): View
    {
        $items = DB::table('prioridades_ticket')
            ->where('proyecto_id', $this->proyectoActivoId())
            ->orderByDesc('peso')
            ->get();

        return view('cx::livewire.admin-prioridades-ticket', ['items' => $items]);
    }
}
