<?php

declare(strict_types=1);

namespace App\Modules\Cx\Infrastructure\Http\Livewire;

use App\Modules\Catalogos\Infrastructure\Http\Livewire\AbstractAdminCatalogo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

/**
 * `nivel` es único por proyecto (unique compuesto). Chequeo adicional en guardar()
 * antes del insert/update.
 */
final class AdminNivelesEscalamiento extends AbstractAdminCatalogo
{
    protected function tabla(): string
    {
        return 'niveles_escalamiento';
    }

    protected function formVacio(): array
    {
        return [
            'codigo' => '',
            'nombre' => '',
            'nivel' => 1,
            'orden' => 100,
            'activo' => true,
        ];
    }

    protected function reglasValidacion(): array
    {
        return [
            'form.codigo' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9_]+$/'],
            'form.nombre' => ['required', 'string', 'max:150'],
            'form.nivel' => ['required', 'integer', 'min:1', 'max:99'],
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
            'orden' => (int) $row->orden,
            'activo' => (bool) $row->activo,
        ];
    }

    public function guardar(): void
    {
        $this->validate($this->reglasValidacion());

        $proyectoId = $this->proyectoActivoId();
        $nivel = (int) $this->form['nivel'];

        $queryNivel = DB::table('niveles_escalamiento')
            ->where('proyecto_id', $proyectoId)
            ->where('nivel', $nivel);
        if ($this->editandoId !== null) {
            $queryNivel->where('id', '!=', $this->editandoId);
        }
        if ($queryNivel->exists()) {
            $this->addError('form.nivel', 'Ya existe un nivel de escalamiento con ese número en el proyecto.');

            return;
        }

        parent::guardar();
    }

    public function render(): View
    {
        $items = DB::table('niveles_escalamiento')
            ->where('proyecto_id', $this->proyectoActivoId())
            ->orderBy('nivel')
            ->get();

        return view('cx::livewire.admin-niveles-escalamiento', ['items' => $items]);
    }
}
