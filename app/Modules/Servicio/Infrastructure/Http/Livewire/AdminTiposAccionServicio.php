<?php

declare(strict_types=1);

namespace App\Modules\Servicio\Infrastructure\Http\Livewire;

use App\Modules\Catalogos\Infrastructure\Http\Livewire\AbstractAdminCatalogo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

final class AdminTiposAccionServicio extends AbstractAdminCatalogo
{
    protected function tabla(): string
    {
        return 'tipos_accion_servicio';
    }

    protected function formVacio(): array
    {
        return [
            'codigo' => '',
            'nombre' => '',
            'duracion_estimada_horas' => 2,
            'orden' => 100,
            'activo' => true,
        ];
    }

    protected function reglasValidacion(): array
    {
        return [
            'form.codigo' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9_\-\s]*$/'],
            'form.nombre' => ['required', 'string', 'max:150'],
            'form.duracion_estimada_horas' => ['nullable', 'integer', 'min:1', 'max:720'],
            'form.orden' => ['integer', 'min:0'],
            'form.activo' => ['boolean'],
        ];
    }

    protected function payloadDesdeForm(): array
    {
        $dur = $this->form['duracion_estimada_horas'] ?? null;

        return [
            'codigo' => (string) $this->form['codigo'],
            'nombre' => (string) $this->form['nombre'],
            'duracion_estimada_horas' => ($dur === '' || $dur === null) ? null : (int) $dur,
            'orden' => (int) ($this->form['orden'] ?? 100),
            'activo' => (bool) ($this->form['activo'] ?? true),
        ];
    }

    protected function formDesdeFila(object $row): array
    {
        return [
            'codigo' => (string) $row->codigo,
            'nombre' => (string) $row->nombre,
            'duracion_estimada_horas' => $row->duracion_estimada_horas === null ? null : (int) $row->duracion_estimada_horas,
            'orden' => (int) $row->orden,
            'activo' => (bool) $row->activo,
        ];
    }

    public function render(): View
    {
        $items = DB::table('tipos_accion_servicio')
            ->where('proyecto_id', $this->proyectoActivoId())
            ->orderBy('orden')->orderBy('codigo')
            ->get();

        return view('servicio::livewire.admin-tipos-accion-servicio', ['items' => $items]);
    }
}
