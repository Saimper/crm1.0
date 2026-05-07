<?php

declare(strict_types=1);

namespace App\Modules\Venta\Infrastructure\Http\Livewire;

use App\Modules\Catalogos\Infrastructure\Http\Livewire\AbstractAdminCatalogo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

/**
 * `nivel` único por proyecto (unique compuesto). Chequeo adicional en guardar().
 */
final class AdminEtapasEmbudo extends AbstractAdminCatalogo
{
    protected function tabla(): string
    {
        return 'etapas_embudo';
    }

    protected function formVacio(): array
    {
        return [
            'codigo' => '',
            'nombre' => '',
            'nivel' => 1,
            'probabilidad_cierre' => 10,
            'orden' => 100,
            'activo' => true,
        ];
    }

    protected function reglasValidacion(): array
    {
        return [
            'form.codigo' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9_\-\s]*$/'],
            'form.nombre' => ['required', 'string', 'max:150'],
            'form.nivel' => ['required', 'integer', 'min:1', 'max:99'],
            'form.probabilidad_cierre' => ['integer', 'min:0', 'max:100'],
            'form.orden' => ['integer', 'min:0'],
            'form.activo' => ['boolean'],
        ];
    }

    protected function payloadDesdeForm(): array
    {
        return [
            'codigo' => (string) $this->form['codigo'],
            'nombre' => (string) $this->form['nombre'],
            'nivel' => (int) $this->form['nivel'],
            'probabilidad_cierre' => (int) ($this->form['probabilidad_cierre'] ?? 0),
            'orden' => (int) ($this->form['orden'] ?? 100),
            'activo' => (bool) ($this->form['activo'] ?? true),
        ];
    }

    protected function formDesdeFila(object $row): array
    {
        return [
            'codigo' => (string) $row->codigo,
            'nombre' => (string) $row->nombre,
            'nivel' => (int) $row->nivel,
            'probabilidad_cierre' => (int) $row->probabilidad_cierre,
            'orden' => (int) $row->orden,
            'activo' => (bool) $row->activo,
        ];
    }

    public function guardar(): void
    {
        $this->validate($this->reglasValidacion());

        $proyectoId = $this->proyectoActivoId();
        $nivel = (int) $this->form['nivel'];

        $q = DB::table('etapas_embudo')->where('proyecto_id', $proyectoId)->where('nivel', $nivel);
        if ($this->editandoId !== null) {
            $q->where('id', '!=', $this->editandoId);
        }
        if ($q->exists()) {
            $this->addError('form.nivel', 'Ya existe una etapa con ese número en el embudo del proyecto.');

            return;
        }

        parent::guardar();
    }

    public function render(): View
    {
        $items = DB::table('etapas_embudo')
            ->where('proyecto_id', $this->proyectoActivoId())
            ->orderBy('nivel')
            ->get();

        return view('venta::livewire.admin-etapas-embudo', ['items' => $items]);
    }
}
