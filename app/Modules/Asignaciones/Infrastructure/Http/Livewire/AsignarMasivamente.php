<?php

declare(strict_types=1);

namespace App\Modules\Asignaciones\Infrastructure\Http\Livewire;

use App\Modules\Asignaciones\Application\UseCases\AsignarCasosAEquipo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Throwable;

/**
 * Reparte de una vez las cuentas sin dueño del proyecto entre los miembros de un
 * equipo, en round-robin. Permiso: asignaciones.reasignar.
 *
 * Flujo:
 *   1. Elige equipo + (opcional) límite.
 *   2. Confirma y dispara el UseCase AsignarCasosAEquipo.
 *   3. Muestra la distribución resultante.
 */
final class AsignarMasivamente extends Component
{
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
            'equipoId' => ['required', 'integer'],
            'limite' => ['integer', 'min:0'],
        ]);

        $proyectoId = (int) app('tenancy.proyecto_activo')->id;

        try {
            $r = $useCase->execute(
                proyectoId: $proyectoId,
                equipoId: (int) $this->equipoId,
                limite: (int) $this->limite,
            );
        } catch (Throwable $e) {
            $this->addError('equipoId', $e->getMessage());

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

        $equipos = DB::table('equipos')
            ->where('proyecto_id', $proyectoId)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre']);

        // Cuántas cuentas hay para repartir. Ya no depende de elegir nada
        // antes: es el mismo criterio de elegibilidad del UseCase.
        $casosSinAsignar = (int) DB::table('casos as c')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('asignaciones as a')
                ->whereColumn('a.caso_id', 'c.id')
                ->where('a.proyecto_id', $proyectoId))
            ->where('c.proyecto_id', $proyectoId)
            ->whereNull('c.cerrado_en')
            ->whereNull('c.eliminada_en')
            ->count();

        $miembrosActivos = null;
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
            'equipos' => $equipos,
            'casosSinAsignar' => $casosSinAsignar,
            'miembrosActivos' => $miembrosActivos,
            'usuariosDistribucion' => $usuariosDistribucion,
        ]);
    }
}
