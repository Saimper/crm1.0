<?php

declare(strict_types=1);

namespace App\Modules\Compromisos\Infrastructure\Http\Livewire;

use App\Models\User;
use App\Modules\Compromisos\Application\DTOs\FiltrosListadoCompromisos;
use App\Modules\Compromisos\Application\Services\ConsultaListadoCompromisos;
use App\Modules\Tenancy\Application\Services\RelojDelMandante;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Listado paginado de compromisos del proyecto activo.
 *
 * Filtros: estado (pendiente/cumplido/roto/cancelado), vencimiento (vigentes/
 * vencidos/proximos7d), tipo_compromiso. Permiso: compromisos.ver.
 *
 * La consulta y los filtros viven en `ConsultaListadoCompromisos`, compartida
 * con la exportación. El «hoy» de los vencimientos es el del cliente.
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
        $filtros = FiltrosListadoCompromisos::desde($this->estado, $this->vencimiento, $this->tipoCompromiso);
        $consulta = app(ConsultaListadoCompromisos::class);

        // En el calendario del cliente: con el servidor en UTC, a las 20:00 en
        // Panamá «vencido» incluía lo que vence mañana.
        $hoy = app(RelojDelMandante::class)->hoy();

        $usuario = Auth::user();
        abort_unless($usuario instanceof User, 401);

        // El límite por cartera del rol (F22) va antes que cualquier filtro.
        $base = $consulta->recortarACarteras($consulta->consultaBase($proyectoId), $usuario->carterasPermitidas($proyectoId));

        $compromisos = $consulta
            ->aplicarFiltros($base, $filtros, $hoy)
            ->select([
                'co.id', 'co.public_id', 'co.tipo_compromiso', 'co.estado',
                'co.fecha_vencimiento', 'co.fecha_resolucion', 'co.creada_en',
                'cs.public_id as caso_public_id', 'cs.tipo_caso',
                'p.public_id as persona_public_id', 'p.tipo_persona',
                'p.nombres', 'p.apellidos', 'p.razon_social', 'p.identificacion',
                'u.name as usuario_nombre',
            ])
            ->orderByDesc('co.fecha_vencimiento')
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
            'hoy' => $hoy,
            'urlExportar' => route('proyectos.compromisos.exportar', ['proyecto_id' => $proyectoId] + $filtros->comoParametros()),
        ]);
    }
}
