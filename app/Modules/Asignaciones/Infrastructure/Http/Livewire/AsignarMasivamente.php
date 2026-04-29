<?php

declare(strict_types=1);

namespace App\Modules\Asignaciones\Infrastructure\Http\Livewire;

use App\Modules\Asignaciones\Application\UseCases\AsignarCasosAEquipo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Throwable;

/**
 * Asigna en batch casos sin asignación de una campaña a los miembros de un equipo
 * via round-robin. Permiso: asignaciones.reasignar.
 *
 * Flujo:
 *   1. Selecciona campaña + equipo + (opcional) límite.
 *   2. Confirma y dispara el UseCase AsignarCasosAEquipo.
 *   3. Muestra distribución resultante.
 */
final class AsignarMasivamente extends Component
{
    public ?int $campanaId = null;
    public ?int $equipoId = null;
    public int $limite = 0;

    public int $ultAsignadas = 0;
    public int $ultOmitidas = 0;

    /** @var array<int, int> usuarioId => cantidad */
    public array $ultDistribucion = [];

    public function asignar(AsignarCasosAEquipo $useCase): void
    {
        abort_unless(auth()->user()?->tienePermiso('asignaciones.reasignar') === true, 403);

        $this->validate([
            'campanaId' => ['required', 'integer'],
            'equipoId'  => ['required', 'integer'],
            'limite'    => ['integer', 'min:0'],
        ]);

        $proyectoId = (int) app('tenancy.proyecto_activo')->id;

        try {
            $r = $useCase->execute(
                proyectoId: $proyectoId,
                campanaId:  (int) $this->campanaId,
                equipoId:   (int) $this->equipoId,
                limite:     (int) $this->limite,
            );
        } catch (Throwable $e) {
            $this->addError('campanaId', $e->getMessage());
            return;
        }

        $this->ultAsignadas = $r->asignadas;
        $this->ultOmitidas = $r->omitidas;
        $this->ultDistribucion = $r->distribucion;

        session()->flash('asignacion-masiva-ok', "{$r->asignadas} casos asignados, {$r->omitidas} omitidos.");
    }

    public function render(): View
    {
        $proyectoId = (int) app('tenancy.proyecto_activo')->id;

        $campanas = DB::table('campanas')
            ->where('proyecto_id', $proyectoId)
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre']);

        $equipos = DB::table('equipos')
            ->where('proyecto_id', $proyectoId)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre']);

        $casosSinAsignar = null;
        $miembrosActivos = null;
        if ($this->campanaId !== null) {
            $casosSinAsignar = (int) DB::table('casos as c')
                ->leftJoin('asignaciones as a', function ($join) {
                    $join->on('a.caso_id', '=', 'c.id')
                         ->where('a.campana_id', '=', $this->campanaId);
                })
                ->where('c.proyecto_id', $proyectoId)
                ->whereNull('c.cerrado_en')
                ->whereNull('c.eliminada_en')
                ->whereNull('a.id')
                ->count();
        }
        if ($this->equipoId !== null) {
            $miembrosActivos = (int) DB::table('equipo_usuario')
                ->where('proyecto_id', $proyectoId)
                ->where('equipo_id', $this->equipoId)
                ->where('activo', true)
                ->count();
        }

        $usuariosDistribucion = [];
        if ($this->ultDistribucion !== []) {
            $usuariosDistribucion = DB::table('users')
                ->whereIn('id', array_keys($this->ultDistribucion))
                ->pluck('name', 'id')
                ->all();
        }

        return view('asignaciones::livewire.asignar-masivamente', [
            'campanas'             => $campanas,
            'equipos'              => $equipos,
            'casosSinAsignar'      => $casosSinAsignar,
            'miembrosActivos'      => $miembrosActivos,
            'usuariosDistribucion' => $usuariosDistribucion,
        ]);
    }
}
