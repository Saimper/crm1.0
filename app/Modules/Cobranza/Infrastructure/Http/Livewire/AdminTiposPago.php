<?php

declare(strict_types=1);

namespace App\Modules\Cobranza\Infrastructure\Http\Livewire;

use App\Modules\Catalogos\Infrastructure\Http\Livewire\AbstractAdminCatalogo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

final class AdminTiposPago extends AbstractAdminCatalogo
{
    protected function tabla(): string
    {
        return 'tipos_pago';
    }

    protected function formVacio(): array
    {
        return ['codigo' => '', 'nombre' => '', 'orden' => 100, 'activo' => true];
    }

    protected function reglasValidacion(): array
    {
        return [
            'form.codigo' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9_\-\s]*$/'],
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
        $items = DB::table('tipos_pago')
            ->where('proyecto_id', $this->proyectoActivoId())
            ->orderBy('orden')
            ->orderBy('codigo')
            ->get();

        return view('cobranza::livewire.admin-tipos-pago', ['items' => $items]);
    }
}
