<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\CatalogosTipo;

use stdClass;

final class CatalogoTiposAccionServicio extends CatalogoTipoBase
{
    protected function tabla(): string
    {
        return 'tipos_accion_servicio';
    }

    protected function formInicial(): array
    {
        return [
            'codigo' => '',
            'nombre' => '',
            'duracion_estimada_horas' => null,
            'orden' => 100,
            'activo' => true,
        ];
    }

    protected function formDesdeRow(stdClass $row): array
    {
        return [
            'codigo' => (string) $row->codigo,
            'nombre' => (string) $row->nombre,
            'duracion_estimada_horas' => $row->duracion_estimada_horas === null
                ? null
                : (int) $row->duracion_estimada_horas,
            'orden' => (int) $row->orden,
            'activo' => (bool) $row->activo,
        ];
    }

    protected function rulesEspecificas(): array
    {
        return [
            'form.duracion_estimada_horas' => ['nullable', 'integer', 'min:0'],
        ];
    }

    protected function labelsEspecificas(): array
    {
        return [
            'form.duracion_estimada_horas' => 'duración estimada (horas)',
        ];
    }

    protected function construirPayload(): array
    {
        $valor = $this->form['duracion_estimada_horas'] ?? null;

        return [
            'duracion_estimada_horas' => $valor === null || $valor === '' ? null : (int) $valor,
        ];
    }

    protected function dependenciasFk(): array
    {
        return [
            ['tabla' => 'casos_servicio', 'columna' => 'tipo_accion_servicio_id'],
            ['tabla' => 'compromisos_accion_servicio', 'columna' => 'tipo_accion_servicio_id'],
        ];
    }

    protected function columnasListado(): array
    {
        return ['id', 'codigo', 'nombre', 'duracion_estimada_horas', 'orden', 'activo'];
    }

    protected function viewSlug(): string
    {
        return 'catalogo-tipos-accion-servicio';
    }

    protected function mensajeFlashClave(): string
    {
        return 'catalogo-tipos-accion-servicio';
    }
}
