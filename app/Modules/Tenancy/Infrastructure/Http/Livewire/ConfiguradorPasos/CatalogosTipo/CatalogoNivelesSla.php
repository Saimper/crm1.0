<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\CatalogosTipo;

use stdClass;

final class CatalogoNivelesSla extends CatalogoTipoBase
{
    protected function tabla(): string
    {
        return 'niveles_sla';
    }

    protected function formInicial(): array
    {
        return ['codigo' => '', 'nombre' => '', 'horas_resolucion' => 24, 'orden' => 100, 'activo' => true];
    }

    protected function formDesdeRow(stdClass $row): array
    {
        return [
            'codigo' => (string) $row->codigo,
            'nombre' => (string) $row->nombre,
            'horas_resolucion' => (int) $row->horas_resolucion,
            'orden' => (int) $row->orden,
            'activo' => (bool) $row->activo,
        ];
    }

    protected function rulesEspecificas(): array
    {
        return [
            'form.horas_resolucion' => ['required', 'integer', 'min:1'],
        ];
    }

    protected function labelsEspecificas(): array
    {
        return [
            'form.horas_resolucion' => 'horas de resolución',
        ];
    }

    protected function construirPayload(): array
    {
        return [
            'horas_resolucion' => (int) ($this->form['horas_resolucion'] ?? 1),
        ];
    }

    protected function dependenciasFk(): array
    {
        return [
            ['tabla' => 'casos_ticket_cx', 'columna' => 'nivel_sla_id'],
        ];
    }

    protected function columnasListado(): array
    {
        return ['id', 'codigo', 'nombre', 'horas_resolucion', 'orden', 'activo'];
    }

    protected function viewSlug(): string
    {
        return 'catalogo-niveles-sla';
    }

    protected function mensajeFlashClave(): string
    {
        return 'catalogo-niveles-sla';
    }
}
