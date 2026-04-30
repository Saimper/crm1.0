<?php

declare(strict_types=1);

namespace App\Modules\Auditoria\Infrastructure\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Lista paginada de eventos de auditoría scoped al proyecto activo.
 * Filtros: entidad_tipo, usuario, evento, rango de fechas.
 */
final class ListadoAuditoria extends Component
{
    use WithPagination;

    public string $entidadTipo = '';

    public ?int $usuarioId = null;

    public string $evento = '';

    public string $desde = '';

    public string $hasta = '';

    public ?int $detalleId = null;

    public function updating(): void
    {
        $this->resetPage();
    }

    public function limpiarFiltros(): void
    {
        $this->reset(['entidadTipo', 'usuarioId', 'evento', 'desde', 'hasta']);
        $this->resetPage();
    }

    public function verDetalle(int $id): void
    {
        $this->detalleId = $id;
    }

    public function cerrarDetalle(): void
    {
        $this->detalleId = null;
    }

    public function render(): View
    {
        $proyectoId = (int) app('tenancy.proyecto_activo')->id;

        $q = DB::table('auditorias as a')
            ->leftJoin('users as u', 'u.id', '=', 'a.usuario_id')
            ->where('a.proyecto_id', $proyectoId)
            ->select([
                'a.id', 'a.entidad_tipo', 'a.entidad_id', 'a.evento',
                'a.ip', 'a.creada_en', 'u.name as usuario_nombre',
            ]);

        if ($this->entidadTipo !== '') {
            $q->where('a.entidad_tipo', $this->entidadTipo);
        }
        if ($this->usuarioId !== null) {
            $q->where('a.usuario_id', $this->usuarioId);
        }
        if ($this->evento !== '') {
            $q->where('a.evento', $this->evento);
        }
        if ($this->desde !== '') {
            $q->where('a.creada_en', '>=', $this->desde.' 00:00:00');
        }
        if ($this->hasta !== '') {
            $q->where('a.creada_en', '<=', $this->hasta.' 23:59:59');
        }

        $registros = $q->orderByDesc('a.creada_en')->paginate(25);

        $tiposEntidad = DB::table('auditorias')
            ->where('proyecto_id', $proyectoId)
            ->distinct()->orderBy('entidad_tipo')
            ->pluck('entidad_tipo')->all();

        $usuarios = DB::table('auditorias as a')
            ->join('users as u', 'u.id', '=', 'a.usuario_id')
            ->where('a.proyecto_id', $proyectoId)
            ->distinct()
            ->select(['u.id', 'u.name'])
            ->orderBy('u.name')
            ->get();

        $detalle = null;
        if ($this->detalleId !== null) {
            $detalle = DB::table('auditorias')
                ->where('proyecto_id', $proyectoId)
                ->where('id', $this->detalleId)
                ->first();
        }

        return view('auditoria::livewire.listado-auditoria', [
            'registros' => $registros,
            'tiposEntidad' => $tiposEntidad,
            'usuarios' => $usuarios,
            'detalle' => $detalle,
        ]);
    }
}
