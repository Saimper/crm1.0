<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\CatalogosTipo;

use stdClass;

final class CatalogoTramosMora extends CatalogoTipoBase
{
    protected function tabla(): string
    {
        return 'tramos_mora';
    }

    protected function formInicial(): array
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

    protected function formDesdeRow(stdClass $row): array
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

    protected function rulesEspecificas(): array
    {
        return [
            'form.dias_desde' => ['required', 'integer', 'min:0'],
            'form.dias_hasta' => ['nullable', 'integer', 'min:0'],
        ];
    }

    protected function labelsEspecificas(): array
    {
        return [
            'form.dias_desde' => 'días desde',
            'form.dias_hasta' => 'días hasta',
        ];
    }

    protected function construirPayload(): array
    {
        $hasta = $this->form['dias_hasta'] ?? null;

        return [
            'dias_desde' => (int) ($this->form['dias_desde'] ?? 0),
            'dias_hasta' => $hasta === null || $hasta === '' ? null : (int) $hasta,
        ];
    }

    protected function dependenciasFk(): array
    {
        return [
            ['tabla' => 'casos_cobranza', 'columna' => 'tramo_mora_id'],
        ];
    }

    protected function columnasListado(): array
    {
        return ['id', 'codigo', 'nombre', 'dias_desde', 'dias_hasta', 'orden', 'activo'];
    }

    protected function viewSlug(): string
    {
        return 'catalogo-tramos-mora';
    }

    protected function mensajeFlashClave(): string
    {
        return 'catalogo-tramos-mora';
    }
}
