<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\CatalogosTipo;

use Illuminate\Support\Facades\DB;
use stdClass;

final class CatalogoCategoriasTicket extends CatalogoTipoBase
{
    protected function tabla(): string
    {
        return 'categorias_ticket';
    }

    protected function formInicial(): array
    {
        return [
            'codigo' => '',
            'nombre' => '',
            'categoria_padre_id' => null,
            'orden' => 100,
            'activo' => true,
        ];
    }

    protected function formDesdeRow(stdClass $row): array
    {
        return [
            'codigo' => (string) $row->codigo,
            'nombre' => (string) $row->nombre,
            'categoria_padre_id' => $row->categoria_padre_id === null ? null : (int) $row->categoria_padre_id,
            'orden' => (int) $row->orden,
            'activo' => (bool) $row->activo,
        ];
    }

    protected function rulesEspecificas(): array
    {
        return [
            'form.categoria_padre_id' => ['nullable', 'integer'],
        ];
    }

    protected function labelsEspecificas(): array
    {
        return [
            'form.categoria_padre_id' => 'categoría padre',
        ];
    }

    protected function construirPayload(): array
    {
        $padre = $this->form['categoria_padre_id'] ?? null;

        return [
            'categoria_padre_id' => $padre === null || $padre === '' ? null : (int) $padre,
        ];
    }

    protected function validarNegocio(int $proyectoId): bool
    {
        $padre = $this->form['categoria_padre_id'] ?? null;
        if ($padre === null || $padre === '') {
            return true;
        }

        $padreId = (int) $padre;

        if ($this->editandoId !== null && $padreId === $this->editandoId) {
            $this->addError('form.categoria_padre_id', 'Una categoría no puede ser su propia padre.');

            return false;
        }

        $perteneceAlProyecto = DB::table('categorias_ticket')
            ->where('id', $padreId)
            ->where('proyecto_id', $proyectoId)
            ->exists();

        if (! $perteneceAlProyecto) {
            $this->addError('form.categoria_padre_id', 'La categoría padre no pertenece al proyecto.');

            return false;
        }

        return true;
    }

    protected function dependenciasFk(): array
    {
        return [
            ['tabla' => 'categorias_ticket', 'columna' => 'categoria_padre_id'],
            ['tabla' => 'casos_ticket_cx', 'columna' => 'categoria_ticket_id'],
        ];
    }

    protected function columnasListado(): array
    {
        return ['id', 'codigo', 'nombre', 'categoria_padre_id', 'orden', 'activo'];
    }

    protected function viewSlug(): string
    {
        return 'catalogo-categorias-ticket';
    }

    protected function mensajeFlashClave(): string
    {
        return 'catalogo-categorias-ticket';
    }

    public function getCategoriasDisponiblesProperty(): array
    {
        $proyectoId = (int) $this->proyecto->id;

        return DB::table('categorias_ticket')
            ->where('proyecto_id', $proyectoId)
            ->when($this->editandoId !== null, fn ($q) => $q->where('id', '!=', $this->editandoId))
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre'])
            ->all();
    }
}
