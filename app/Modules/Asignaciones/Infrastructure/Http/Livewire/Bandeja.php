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
        $proyectoId = (int) app('tenancy.proyecto_activo')->id;
        $usuarioId  = (int) auth()->id();

        $asignacion = DB::table('asignaciones')
            ->where('id', $asignacionId)
            ->where('proyecto_id', $proyectoId)
            ->where('usuario_id', $usuarioId)
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
        $proyectoActivo = app('tenancy.proyecto_activo');
        $proyectoId     = (int) $proyectoActivo->id;
        $usuarioId      = (int) auth()->id();

        $query = DB::table('asignaciones as a')
            ->join('casos as c',              'c.id',  '=', 'a.caso_id')
            ->join('personas as pe',          'pe.id', '=', 'c.persona_id')
            ->join('carteras as ca',          'ca.id', '=', 'c.cartera_id')
            ->join('estados_caso as ec',      'ec.id', '=', 'c.estado_caso_id')
            ->leftJoin('resultados as ru',    'ru.id', '=', 'c.resultado_ultima_gestion_id')
            ->leftJoin('campanas as cm',      'cm.id', '=', 'a.campana_id')
            ->where('a.proyecto_id', $proyectoId)
            ->where('a.usuario_id', $usuarioId)
            ->whereNull('c.eliminada_en');

        if ($this->estadoFiltro !== 'todos') {
            $query->where('a.estado', $this->estadoFiltro);
        }

        $texto = trim($this->busqueda);
        if ($texto !== '') {
            $like = "%{$texto}%";
            $query->where(function ($w) use ($like): void {
                $w->where('pe.identificacion', 'like', $like)
                    ->orWhere('pe.nombres',      'like', $like)
                    ->orWhere('pe.apellidos',    'like', $like)
                    ->orWhere('pe.razon_social', 'like', $like);
            });
        }

        $asignaciones = $query
            ->select([
                'a.id', 'a.public_id as asignacion_public_id', 'a.estado',
                'a.prioridad', 'a.fecha_asignacion',
                'c.public_id as caso_public_id', 'c.tipo_caso',
                'c.fecha_ultima_gestion', 'c.tiene_compromiso_vigente',
                'pe.public_id as persona_public_id',
                'pe.identificacion', 'pe.tipo_persona',
                'pe.nombres', 'pe.apellidos', 'pe.razon_social',
                'ec.nombre as estado_caso_nombre', 'ec.codigo as estado_caso_codigo',
                'ca.nombre as cartera_nombre',
                'ru.nombre as resultado_ultimo',
                'cm.nombre as campana_nombre',
            ])
            ->orderByDesc('a.prioridad')
            ->orderByDesc('c.fecha_ultima_gestion')
            ->paginate(20);

        $conteoPorEstado = DB::table('asignaciones')
            ->where('proyecto_id', $proyectoId)
            ->where('usuario_id', $usuarioId)
            ->selectRaw('estado, count(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        return view('asignaciones::livewire.bandeja', [
            'asignaciones'    => $asignaciones,
            'conteoPorEstado' => $conteoPorEstado,
            'totalGeneral'    => (int) $conteoPorEstado->sum(),
            'proyectoActivo'  => $proyectoActivo,
        ]);
    }
}
