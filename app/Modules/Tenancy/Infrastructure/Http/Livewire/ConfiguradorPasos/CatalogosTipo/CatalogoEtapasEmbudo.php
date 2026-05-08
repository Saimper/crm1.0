<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\CatalogosTipo;

use Illuminate\Support\Facades\DB;
use stdClass;

final class CatalogoEtapasEmbudo extends CatalogoTipoBase
{
    protected function tabla(): string
    {
        return 'etapas_embudo';
    }

    protected function formInicial(): array
    {
        return [
            'codigo' => '',
            'nombre' => '',
            'nivel' => 1,
            'probabilidad_cierre' => 0,
            'orden' => 100,
            'activo' => true,
        ];
    }

    protected function formDesdeRow(stdClass $row): array
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

    protected function rulesEspecificas(): array
    {
        return [
            'form.nivel' => ['required', 'integer', 'min:1'],
            'form.probabilidad_cierre' => ['required', 'integer', 'min:0', 'max:100'],
        ];
    }

    protected function labelsEspecificas(): array
    {
        return [
            'form.nivel' => 'nivel',
            'form.probabilidad_cierre' => 'probabilidad de cierre',
        ];
    }

    protected function construirPayload(): array
    {
        return [
            'nivel' => (int) ($this->form['nivel'] ?? 1),
            'probabilidad_cierre' => (int) ($this->form['probabilidad_cierre'] ?? 0),
        ];
    }

    protected function validarNegocio(int $proyectoId): bool
    {
        $nivel = (int) ($this->form['nivel'] ?? 0);

        $duplicado = DB::table('etapas_embudo')
            ->where('proyecto_id', $proyectoId)
            ->where('nivel', $nivel)
            ->when($this->editandoId !== null, fn ($q) => $q->where('id', '!=', $this->editandoId))
            ->exists();

        if ($duplicado) {
            $this->addError('form.nivel', 'Ya existe otra etapa con ese nivel en el proyecto.');

            return false;
        }

        return true;
    }

    protected function dependenciasFk(): array
    {
        return [
            ['tabla' => 'casos_lead_venta', 'columna' => 'etapa_embudo_id'],
            ['tabla' => 'compromisos_cierre_venta', 'columna' => 'etapa_embudo_id'],
        ];
    }

    protected function columnasListado(): array
    {
        return ['id', 'codigo', 'nombre', 'nivel', 'probabilidad_cierre', 'orden', 'activo'];
    }

    protected function viewSlug(): string
    {
        return 'catalogo-etapas-embudo';
    }

    protected function mensajeFlashClave(): string
    {
        return 'catalogo-etapas-embudo';
    }
}
