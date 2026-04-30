<?php

declare(strict_types=1);

namespace App\Modules\Compromisos\Infrastructure\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Listado paginado de compromisos del proyecto activo.
 *
 * Filtros: estado (pendiente/cumplido/roto/cancelado), vencimiento (vigentes/
 * vencidos/proximos7d), tipo_compromiso. Permiso: compromisos.ver.
 */
final class ListadoCompromisos extends Component
{
    use WithPagination;

    #[Url(as: 'estado', except: '')]
    public string $estado = '';

    #[Url(as: 'venc', except: '')]
    public string $vencimiento = '';

    #[Url(as: 'tipo', except: '')]
    public string $tipoCompromiso = '';

    public function updatingEstado(): void
    {
        $this->resetPage();
    }

    public function updatingVencimiento(): void
    {
        $this->resetPage();
    }

    public function updatingTipoCompromiso(): void
    {
        $this->resetPage();
    }

    public function limpiarFiltros(): void
    {
        $this->estado = '';
        $this->vencimiento = '';
        $this->tipoCompromiso = '';
        $this->resetPage();
    }

    public function render(): View
    {
        $proyectoId = (int) app('tenancy.proyecto_activo')->id;

        $q = DB::table('compromisos as c')
            ->leftJoin('casos as cs', 'cs.id', '=', 'c.caso_id')
            ->leftJoin('personas as p', 'p.id', '=', 'cs.persona_id')
            ->leftJoin('users as u', 'u.id', '=', 'c.usuario_id')
            ->where('c.proyecto_id', $proyectoId)
            ->whereNull('c.eliminada_en');

        if (in_array($this->estado, ['pendiente', 'cumplido', 'roto', 'cancelado'], true)) {
            $q->where('c.estado', $this->estado);
        }

        $hoy = Carbon::today()->toDateString();
        match ($this->vencimiento) {
            'vigentes' => $q->where('c.estado', 'pendiente')->where('c.fecha_vencimiento', '>=', $hoy),
            'vencidos' => $q->where('c.estado', 'pendiente')->where('c.fecha_vencimiento', '<', $hoy),
            'proximos7d' => $q->where('c.estado', 'pendiente')
                ->whereBetween('c.fecha_vencimiento', [$hoy, Carbon::today()->addDays(7)->toDateString()]),
            default => null,
        };

        if (in_array($this->tipoCompromiso, ['promesa_pago', 'resolucion_ticket', 'cierre_venta', 'accion_servicio'], true)) {
            $q->where('c.tipo_compromiso', $this->tipoCompromiso);
        }

        $compromisos = $q
            ->select([
                'c.id', 'c.public_id', 'c.tipo_compromiso', 'c.estado',
                'c.fecha_vencimiento', 'c.fecha_resolucion', 'c.creada_en',
                'cs.public_id as caso_public_id', 'cs.tipo_caso',
                'p.public_id as persona_public_id', 'p.tipo_persona',
                'p.nombres', 'p.apellidos', 'p.razon_social', 'p.identificacion',
                'u.name as usuario_nombre',
            ])
            ->orderByDesc('c.fecha_vencimiento')
            ->paginate(25);

        $resumen = [
            'pendientes' => (int) DB::table('compromisos')
                ->where('proyecto_id', $proyectoId)->whereNull('eliminada_en')
                ->where('estado', 'pendiente')->count(),
            'vencidos' => (int) DB::table('compromisos')
                ->where('proyecto_id', $proyectoId)->whereNull('eliminada_en')
                ->where('estado', 'pendiente')->where('fecha_vencimiento', '<', $hoy)->count(),
            'cumplidos' => (int) DB::table('compromisos')
                ->where('proyecto_id', $proyectoId)->whereNull('eliminada_en')
                ->where('estado', 'cumplido')->count(),
            'rotos' => (int) DB::table('compromisos')
                ->where('proyecto_id', $proyectoId)->whereNull('eliminada_en')
                ->where('estado', 'roto')->count(),
        ];

        return view('compromisos::livewire.listado-compromisos', [
            'compromisos' => $compromisos,
            'resumen' => $resumen,
        ]);
    }
}
