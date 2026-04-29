<?php

declare(strict_types=1);

namespace App\Modules\Venta\Infrastructure\Http\Livewire;

use App\Modules\Catalogos\Infrastructure\Http\Livewire\AbstractAdminCatalogo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

final class AdminProductosVenta extends AbstractAdminCatalogo
{
    protected function tabla(): string
    {
        return 'productos_venta';
    }

    protected function formVacio(): array
    {
        return [
            'codigo'      => '',
            'nombre'      => '',
            'descripcion' => '',
            'orden'       => 100,
            'activo'      => true,
        ];
    }

    protected function reglasValidacion(): array
    {
        return [
            'form.codigo'      => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9_]+$/'],
            'form.nombre'      => ['required', 'string', 'max:200'],
            'form.descripcion' => ['nullable', 'string', 'max:500'],
            'form.orden'       => ['integer', 'min:0'],
            'form.activo'      => ['boolean'],
        ];
    }

    protected function payloadDesdeForm(): array
    {
        return [
            'codigo'      => (string) $this->form['codigo'],
            'nombre'      => (string) $this->form['nombre'],
            'descripcion' => ($this->form['descripcion'] ?? '') !== '' ? (string) $this->form['descripcion'] : null,
            'orden'       => (int) ($this->form['orden'] ?? 100),
            'activo'      => (bool) ($this->form['activo'] ?? true),
        ];
    }

    protected function formDesdeFila(object $row): array
    {
        return [
            'codigo'      => (string) $row->codigo,
            'nombre'      => (string) $row->nombre,
            'descripcion' => (string) ($row->descripcion ?? ''),
            'orden'       => (int) $row->orden,
            'activo'      => (bool) $row->activo,
        ];
    }

    public function render(): View
    {
        $items = DB::table('productos_venta')
            ->where('proyecto_id', $this->proyectoActivoId())
            ->orderBy('orden')->orderBy('codigo')
            ->get();

        return view('venta::livewire.admin-productos-venta', ['items' => $items]);
    }
}
