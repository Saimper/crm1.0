<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\CatalogosTipo;

use stdClass;

final class CatalogoPrioridadesTicket extends CatalogoTipoBase
{
    protected function tabla(): string
    {
        return 'prioridades_ticket';
    }

    protected function formInicial(): array
    {
        return ['codigo' => '', 'nombre' => '', 'peso' => 100, 'orden' => 100, 'activo' => true];
    }

    protected function formDesdeRow(stdClass $row): array
    {
        return [
            'codigo' => (string) $row->codigo,
            'nombre' => (string) $row->nombre,
            'peso' => (int) $row->peso,
            'orden' => (int) $row->orden,
            'activo' => (bool) $row->activo,
        ];
    }

    protected function rulesEspecificas(): array
    {
        return [
            'form.peso' => ['required', 'integer', 'min:0'],
        ];
    }

    protected function labelsEspecificas(): array
    {
        return [
            'form.peso' => 'peso',
        ];
    }

    protected function construirPayload(): array
    {
        return [
            'peso' => (int) ($this->form['peso'] ?? 0),
        ];
    }

    protected function dependenciasFk(): array
    {
        return [
            ['tabla' => 'casos_ticket_cx', 'columna' => 'prioridad_ticket_id'],
        ];
    }

    protected function columnasListado(): array
    {
        return ['id', 'codigo', 'nombre', 'peso', 'orden', 'activo'];
    }

    protected function viewSlug(): string
    {
        return 'catalogo-prioridades-ticket';
    }

    protected function mensajeFlashClave(): string
    {
        return 'catalogo-prioridades-ticket';
    }
}
