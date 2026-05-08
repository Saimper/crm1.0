<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\CatalogosTipo;

use stdClass;

final class CatalogoTiposPago extends CatalogoTipoBase
{
    protected function tabla(): string
    {
        return 'tipos_pago';
    }

    protected function formInicial(): array
    {
        return ['codigo' => '', 'nombre' => '', 'orden' => 100, 'activo' => true];
    }

    protected function formDesdeRow(stdClass $row): array
    {
        return [
            'codigo' => (string) $row->codigo,
            'nombre' => (string) $row->nombre,
            'orden' => (int) $row->orden,
            'activo' => (bool) $row->activo,
        ];
    }

    protected function rulesEspecificas(): array
    {
        return [];
    }

    protected function labelsEspecificas(): array
    {
        return [];
    }

    protected function construirPayload(): array
    {
        return [];
    }

    protected function dependenciasFk(): array
    {
        return [
            ['tabla' => 'compromisos_promesa_pago', 'columna' => 'tipo_pago_id'],
        ];
    }

    protected function columnasListado(): array
    {
        return ['id', 'codigo', 'nombre', 'orden', 'activo'];
    }

    protected function viewSlug(): string
    {
        return 'catalogo-tipos-pago';
    }

    protected function mensajeFlashClave(): string
    {
        return 'catalogo-tipos-pago';
    }
}
