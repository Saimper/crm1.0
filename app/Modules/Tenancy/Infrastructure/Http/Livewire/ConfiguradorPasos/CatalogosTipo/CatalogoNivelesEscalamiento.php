<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\CatalogosTipo;

use Illuminate\Support\Facades\DB;
use stdClass;

final class CatalogoNivelesEscalamiento extends CatalogoTipoBase
{
    protected function tabla(): string
    {
        return 'niveles_escalamiento';
    }

    protected function formInicial(): array
    {
        return ['codigo' => '', 'nombre' => '', 'nivel' => 1, 'orden' => 100, 'activo' => true];
    }

    protected function formDesdeRow(stdClass $row): array
    {
        return [
            'codigo' => (string) $row->codigo,
            'nombre' => (string) $row->nombre,
            'nivel' => (int) $row->nivel,
            'orden' => (int) $row->orden,
            'activo' => (bool) $row->activo,
        ];
    }

    protected function rulesEspecificas(): array
    {
        return [
            'form.nivel' => ['required', 'integer', 'min:1'],
        ];
    }

    protected function labelsEspecificas(): array
    {
        return [
            'form.nivel' => 'nivel',
        ];
    }

    protected function construirPayload(): array
    {
        return ['nivel' => (int) ($this->form['nivel'] ?? 1)];
    }

    protected function validarNegocio(int $proyectoId): bool
    {
        $nivel = (int) ($this->form['nivel'] ?? 0);

        $duplicado = DB::table('niveles_escalamiento')
            ->where('proyecto_id', $proyectoId)
            ->where('nivel', $nivel)
            ->when($this->editandoId !== null, fn ($q) => $q->where('id', '!=', $this->editandoId))
            ->exists();

        if ($duplicado) {
            $this->addError('form.nivel', 'Ya existe otro nivel con ese número en el proyecto.');

            return false;
        }

        return true;
    }

    protected function dependenciasFk(): array
    {
        return [
            ['tabla' => 'casos_ticket_cx', 'columna' => 'nivel_escalamiento_id'],
            ['tabla' => 'compromisos_resolucion_ticket', 'columna' => 'nivel_escalamiento_id'],
        ];
    }

    protected function columnasListado(): array
    {
        return ['id', 'codigo', 'nombre', 'nivel', 'orden', 'activo'];
    }

    protected function viewSlug(): string
    {
        return 'catalogo-niveles-escalamiento';
    }

    protected function mensajeFlashClave(): string
    {
        return 'catalogo-niveles-escalamiento';
    }
}
