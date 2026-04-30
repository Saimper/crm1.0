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
        $modoGlobal = ! app()->bound('tenancy.proyecto_activo');
        $proyectoId = $modoGlobal ? null : (int) app('tenancy.proyecto_activo')->id;

        $q = DB::table('auditorias as a')
            ->leftJoin('users as u', 'u.id', '=', 'a.usuario_id')
            ->leftJoin('proyectos as p', 'p.id', '=', 'a.proyecto_id')
            ->select([
                'a.id', 'a.entidad_tipo', 'a.entidad_id', 'a.evento',
                'a.ip', 'a.creada_en', 'a.proyecto_id',
                'u.name as usuario_nombre',
                'p.codigo as proyecto_codigo', 'p.nombre as proyecto_nombre',
            ]);

        if ($modoGlobal) {
            // Sin scope de proyecto: ADMIN_GLOBAL ve todo (incluyendo nullables).
        } else {
            $q->where('a.proyecto_id', $proyectoId);
        }

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

        $tiposQ = DB::table('auditorias');
        if (! $modoGlobal) {
            $tiposQ->where('proyecto_id', $proyectoId);
        }
        $tiposEntidad = $tiposQ->distinct()->orderBy('entidad_tipo')
            ->pluck('entidad_tipo')->all();

        $usuariosQ = DB::table('auditorias as a')
            ->join('users as u', 'u.id', '=', 'a.usuario_id');
        if (! $modoGlobal) {
            $usuariosQ->where('a.proyecto_id', $proyectoId);
        }
        $usuarios = $usuariosQ->distinct()
            ->select(['u.id', 'u.name'])
            ->orderBy('u.name')
            ->get();

        $detalle = null;
        if ($this->detalleId !== null) {
            $detalleQ = DB::table('auditorias')->where('id', $this->detalleId);
            if (! $modoGlobal) {
                $detalleQ->where('proyecto_id', $proyectoId);
            }
            $detalle = $detalleQ->first();
        }

        return view('auditoria::livewire.listado-auditoria', [
            'registros' => $registros,
            'tiposEntidad' => $tiposEntidad,
            'usuarios' => $usuarios,
            'detalle' => $detalle,
            'modoGlobal' => $modoGlobal,
        ]);
    }
}
