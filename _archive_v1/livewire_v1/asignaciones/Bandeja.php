<?php

declare(strict_types=1);

namespace App\Modules\Asignaciones\Infrastructure\Http\Livewire;

use App\Modules\Asignaciones\Application\UseCases\CerrarAsignacion;
use App\Modules\Asignaciones\Domain\Exceptions\TransicionAsignacionInvalida;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

final class Bandeja extends Component
{
    use WithPagination;

    #[Url(as: 'estado')]
    public string $estadoFiltro = 'pendiente';

    #[Url(as: 'q', except: '')]
    public string $busqueda = '';

    public ?string $mensajeExito = null;

    public function updatedEstadoFiltro(): void
    {
        $this->resetPage();
    }

    public function updatedBusqueda(): void
    {
        $this->resetPage();
    }

    public function cerrarAsignacion(int $asignacionId, CerrarAsignacion $useCase): void
    {
        $asignacion = DB::table('asignaciones')
            ->where('id', $asignacionId)
            ->where('usuario_id', auth()->id())
            ->first();

        abort_unless($asignacion, 404);

        try {
            $useCase->execute($asignacionId);
            $this->mensajeExito = 'Asignación cerrada.';
        } catch (TransicionAsignacionInvalida $e) {
            $this->addError('asignacion', $e->getMessage());
        }
    }

    public function render(): View
    {
        $usuarioId = (int) auth()->id();

        $query = DB::table('asignaciones as a')
            ->join('productos as p', 'p.id', '=', 'a.producto_id')
            ->join('clientes as c', 'c.id', '=', 'p.cliente_id')
            ->join('estados_producto as ep', 'ep.id', '=', 'p.estado_producto_id')
            ->leftJoin('resultados as ru', 'ru.id', '=', 'p.resultado_ultima_gestion_id')
            ->leftJoin('campanas as cm', 'cm.id', '=', 'a.campana_id')
            ->where('a.usuario_id', $usuarioId)
            ->whereNull('p.eliminada_en');

        if ($this->estadoFiltro !== 'todos') {
            $query->where('a.estado', $this->estadoFiltro);
        }

        $texto = trim($this->busqueda);
        if ($texto !== '') {
            $like = "%{$texto}%";
            $query->where(function ($w) use ($like): void {
                $w->where('p.numero_prestamo', 'like', $like)
                    ->orWhere('c.identificacion', 'like', $like)
                    ->orWhere('c.nombres', 'like', $like)
                    ->orWhere('c.apellidos', 'like', $like)
                    ->orWhere('c.razon_social', 'like', $like);
            });
        }

        $asignaciones = $query
            ->select([
                'a.id', 'a.public_id as asignacion_public_id', 'a.estado',
                'a.prioridad', 'a.fecha_asignacion',
                'p.public_id as producto_public_id', 'p.numero_prestamo',
                'p.saldo_total', 'p.moneda', 'p.dias_mora',
                'p.fecha_ultima_gestion', 'p.tiene_promesa_vigente',
                'c.public_id as cliente_public_id', 'c.identificacion',
                'c.nombres', 'c.apellidos', 'c.razon_social', 'c.tipo_persona',
                'ep.nombre as estado_producto_nombre',
                'ru.nombre as resultado_ultimo',
                'cm.nombre as campana_nombre',
            ])
            ->orderByDesc('a.prioridad')
            ->orderByDesc('p.dias_mora')
            ->paginate(20);

        $conteoPorEstado = DB::table('asignaciones')
            ->where('usuario_id', $usuarioId)
            ->selectRaw('estado, count(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        return view('asignaciones::livewire.bandeja', [
            'asignaciones' => $asignaciones,
            'conteoPorEstado' => $conteoPorEstado,
            'totalGeneral' => (int) $conteoPorEstado->sum(),
        ]);
    }
}
