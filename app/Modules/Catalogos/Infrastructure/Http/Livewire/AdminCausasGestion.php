<?php

declare(strict_types=1);

namespace App\Modules\Catalogos\Infrastructure\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

/**
 * CRUD de causas_gestion del proyecto. Incluye `metadata->tipo` (mora/queja/rechazo/servicio)
 * que se expone como selector según tipo_operacion del proyecto.
 */
final class AdminCausasGestion extends AbstractAdminCatalogo
{
    protected function tabla(): string
    {
        return 'causas_gestion';
    }

    protected function formVacio(): array
    {
        return [
            'codigo' => '',
            'nombre' => '',
            'tipo' => $this->tipoPorDefecto(),
            'orden' => 100,
            'activo' => true,
        ];
    }

    protected function reglasValidacion(): array
    {
        return [
            'form.codigo' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9_]+$/'],
            'form.nombre' => ['required', 'string', 'max:150'],
            'form.tipo' => ['nullable', 'in:mora,queja,rechazo,servicio,otra'],
            'form.orden' => ['integer', 'min:0'],
            'form.activo' => ['boolean'],
        ];
    }

    protected function payloadDesdeForm(): array
    {
        $tipo = (string) ($this->form['tipo'] ?? '');

        return [
            'codigo' => (string) $this->form['codigo'],
            'nombre' => (string) $this->form['nombre'],
            'orden' => (int) ($this->form['orden'] ?? 100),
            'activo' => (bool) ($this->form['activo'] ?? true),
            'metadata' => $tipo !== '' ? json_encode(['tipo' => $tipo]) : null,
        ];
    }

    protected function formDesdeFila(object $row): array
    {
        $meta = is_string($row->metadata) ? (array) json_decode($row->metadata, true) : [];

        return [
            'codigo' => (string) $row->codigo,
            'nombre' => (string) $row->nombre,
            'tipo' => (string) ($meta['tipo'] ?? ''),
            'orden' => (int) $row->orden,
            'activo' => (bool) $row->activo,
        ];
    }

    public function render(): View
    {
        $items = DB::table('causas_gestion')
            ->where('proyecto_id', $this->proyectoActivoId())
            ->orderBy('orden')
            ->orderBy('codigo')
            ->get();

        return view('catalogos::livewire.admin-causas-gestion', ['items' => $items]);
    }

    private function tipoPorDefecto(): string
    {
        $tipoOp = (string) (app('tenancy.proyecto_activo')->tipo_operacion ?? '');

        return match ($tipoOp) {
            'cobranza' => 'mora',
            'cx' => 'queja',
            'venta' => 'rechazo',
            'servicio' => 'servicio',
            default => '',
        };
    }
}
