<?php

declare(strict_types=1);

namespace App\Modules\Cobranza\Infrastructure\Http\Livewire;

use App\Modules\Catalogos\Infrastructure\Http\Livewire\AbstractAdminCatalogo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

final class AdminTramosMora extends AbstractAdminCatalogo
{
    protected function tabla(): string
    {
        return 'tramos_mora';
    }

    protected function formVacio(): array
    {
        return [
            'codigo' => '',
            'nombre' => '',
            'dias_desde' => 0,
            'dias_hasta' => null,
            'orden' => 100,
            'activo' => true,
        ];
    }

    protected function reglasValidacion(): array
    {
        return [
            'form.codigo' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9_]+$/'],
            'form.nombre' => ['required', 'string', 'max:150'],
            'form.dias_desde' => ['required', 'integer', 'min:0'],
            'form.dias_hasta' => ['nullable', 'integer', 'min:0', 'gte:form.dias_desde'],
            'form.orden' => ['integer', 'min:0'],
            'form.activo' => ['boolean'],
        ];
    }

    protected function payloadDesdeForm(): array
    {
        $hasta = $this->form['dias_hasta'] ?? null;

        return [
            'codigo' => (string) $this->form['codigo'],
            'nombre' => (string) $this->form['nombre'],
            'dias_desde' => (int) ($this->form['dias_desde'] ?? 0),
            'dias_hasta' => ($hasta === '' || $hasta === null) ? null : (int) $hasta,
            'orden' => (int) ($this->form['orden'] ?? 100),
            'activo' => (bool) ($this->form['activo'] ?? true),
        ];
    }

    protected function formDesdeFila(object $row): array
    {
        return [
            'codigo' => (string) $row->codigo,
            'nombre' => (string) $row->nombre,
            'dias_desde' => (int) $row->dias_desde,
            'dias_hasta' => $row->dias_hasta === null ? null : (int) $row->dias_hasta,
            'orden' => (int) $row->orden,
            'activo' => (bool) $row->activo,
        ];
    }

    public function render(): View
    {
        $items = DB::table('tramos_mora')
            ->where('proyecto_id', $this->proyectoActivoId())
            ->orderBy('dias_desde')
            ->get();

        return view('cobranza::livewire.admin-tramos-mora', ['items' => $items]);
    }
}
