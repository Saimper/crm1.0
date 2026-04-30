<?php

declare(strict_types=1);

namespace App\Modules\Servicio\Infrastructure\Http\Livewire;

use App\Modules\Catalogos\Infrastructure\Http\Livewire\AbstractAdminCatalogo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

final class AdminEstadosTecnicos extends AbstractAdminCatalogo
{
    protected function tabla(): string
    {
        return 'estados_tecnicos';
    }

    protected function formVacio(): array
    {
        return ['codigo' => '', 'nombre' => '', 'orden' => 100, 'activo' => true];
    }

    protected function reglasValidacion(): array
    {
        return [
            'form.codigo' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9_]+$/'],
            'form.nombre' => ['required', 'string', 'max:150'],
            'form.orden' => ['integer', 'min:0'],
            'form.activo' => ['boolean'],
        ];
    }

    protected function payloadDesdeForm(): array
    {
        return [
            'codigo' => (string) $this->form['codigo'],
            'nombre' => (string) $this->form['nombre'],
            'orden' => (int) ($this->form['orden'] ?? 100),
            'activo' => (bool) ($this->form['activo'] ?? true),
        ];
    }

    protected function formDesdeFila(object $row): array
    {
        return [
            'codigo' => (string) $row->codigo,
            'nombre' => (string) $row->nombre,
            'orden' => (int) $row->orden,
            'activo' => (bool) $row->activo,
        ];
    }

    public function render(): View
    {
        $items = DB::table('estados_tecnicos')
            ->where('proyecto_id', $this->proyectoActivoId())
            ->orderBy('orden')->orderBy('codigo')
            ->get();

        return view('servicio::livewire.admin-estados-tecnicos', ['items' => $items]);
    }
}
