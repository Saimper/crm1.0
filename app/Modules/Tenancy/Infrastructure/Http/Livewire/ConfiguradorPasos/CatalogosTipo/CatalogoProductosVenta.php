<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\CatalogosTipo;

use stdClass;

final class CatalogoProductosVenta extends CatalogoTipoBase
{
    protected function tabla(): string
    {
        return 'productos_venta';
    }

    protected function formInicial(): array
    {
        return [
            'codigo' => '',
            'nombre' => '',
            'descripcion' => '',
            'orden' => 100,
            'activo' => true,
        ];
    }

    protected function formDesdeRow(stdClass $row): array
    {
        return [
            'codigo' => (string) $row->codigo,
            'nombre' => (string) $row->nombre,
            'descripcion' => (string) ($row->descripcion ?? ''),
            'orden' => (int) $row->orden,
            'activo' => (bool) $row->activo,
        ];
    }

    protected function rulesEspecificas(): array
    {
        return [
            'form.descripcion' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function labelsEspecificas(): array
    {
        return [
            'form.descripcion' => 'descripción',
        ];
    }

    protected function construirPayload(): array
    {
        $descripcion = trim((string) ($this->form['descripcion'] ?? ''));

        return [
            'descripcion' => $descripcion === '' ? null : $descripcion,
        ];
    }

    protected function dependenciasFk(): array
    {
        return [
            ['tabla' => 'casos_lead_venta', 'columna' => 'producto_venta_id'],
        ];
    }

    protected function columnasListado(): array
    {
        return ['id', 'codigo', 'nombre', 'descripcion', 'orden', 'activo'];
    }

    protected function viewSlug(): string
    {
        return 'catalogo-productos-venta';
    }

    protected function mensajeFlashClave(): string
    {
        return 'catalogo-productos-venta';
    }
}
