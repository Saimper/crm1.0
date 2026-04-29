<?php

declare(strict_types=1);

namespace App\Modules\Cx\Infrastructure\Http\Livewire;

use App\Modules\Catalogos\Infrastructure\Http\Livewire\AbstractAdminCatalogo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

final class AdminNivelesSla extends AbstractAdminCatalogo
{
    protected function tabla(): string
    {
        return 'niveles_sla';
    }

    protected function formVacio(): array
    {
        return [
            'codigo'           => '',
            'nombre'           => '',
            'horas_resolucion' => 24,
            'orden'            => 100,
            'activo'           => true,
        ];
    }

    protected function reglasValidacion(): array
    {
        return [
            'form.codigo'           => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9_]+$/'],
            'form.nombre'           => ['required', 'string', 'max:150'],
            'form.horas_resolucion' => ['required', 'integer', 'min:1'],
            'form.orden'            => ['integer', 'min:0'],
            'form.activo'           => ['boolean'],
        ];
    }

    protected function payloadDesdeForm(): array
    {
        return [
            'codigo'           => (string) $this->form['codigo'],
            'nombre'           => (string) $this->form['nombre'],
            'horas_resolucion' => (int) $this->form['horas_resolucion'],
            'orden'            => (int) ($this->form['orden'] ?? 100),
            'activo'           => (bool) ($this->form['activo'] ?? true),
        ];
    }

    protected function formDesdeFila(object $row): array
    {
        return [
            'codigo'           => (string) $row->codigo,
            'nombre'           => (string) $row->nombre,
            'horas_resolucion' => (int) $row->horas_resolucion,
            'orden'            => (int) $row->orden,
            'activo'           => (bool) $row->activo,
        ];
    }

    public function render(): View
    {
        $items = DB::table('niveles_sla')
            ->where('proyecto_id', $this->proyectoActivoId())
            ->orderBy('horas_resolucion')
            ->get();

        return view('cx::livewire.admin-niveles-sla', ['items' => $items]);
    }
}
