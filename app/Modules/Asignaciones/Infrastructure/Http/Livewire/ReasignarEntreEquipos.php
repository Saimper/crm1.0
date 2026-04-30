<?php

declare(strict_types=1);

namespace App\Modules\Asignaciones\Infrastructure\Http\Livewire;

use App\Modules\Asignaciones\Application\UseCases\ReasignarCasosEntreEquipos;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Throwable;

/**
 * Mueve asignaciones pendientes de un equipo a otro. Permiso: asignaciones.reasignar.
 */
final class ReasignarEntreEquipos extends Component
{
    public ?int $equipoOrigenId = null;

    public ?int $equipoDestinoId = null;

    public int $limite = 0;

    public int $ultMovidas = 0;

    /** @var array<int, int> */
    public array $ultDistribucion = [];

    public function reasignar(ReasignarCasosEntreEquipos $useCase): void
    {
        abort_unless(auth()->user()?->tienePermiso('asignaciones.reasignar') === true, 403);

        $this->validate([
            'equipoOrigenId' => ['required', 'integer'],
            'equipoDestinoId' => ['required', 'integer', 'different:equipoOrigenId'],
            'limite' => ['integer', 'min:0'],
        ], [
            'equipoDestinoId.different' => 'Origen y destino deben ser distintos.',
        ]);

        $proyectoId = (int) app('tenancy.proyecto_activo')->id;

        try {
            $r = $useCase->execute(
                proyectoId: $proyectoId,
                equipoOrigenId: (int) $this->equipoOrigenId,
                equipoDestinoId: (int) $this->equipoDestinoId,
                limite: (int) $this->limite,
            );
        } catch (Throwable $e) {
            $this->addError('equipoOrigenId', $e->getMessage());

            return;
        }

        $this->ultMovidas = $r->asignadas;
        $this->ultDistribucion = $r->distribucion;
        session()->flash('reasignacion-ok', "{$r->asignadas} asignaciones movidas.");
    }

    public function render(): View
    {
        $proyectoId = (int) app('tenancy.proyecto_activo')->id;

        $equipos = DB::table('equipos')
            ->where('proyecto_id', $proyectoId)
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre', 'activo']);

        $pendientesOrigen = null;
        $miembrosDestino = null;

        if ($this->equipoOrigenId !== null) {
            $miembrosOrigenIds = DB::table('equipo_usuario')
                ->where('proyecto_id', $proyectoId)
                ->where('equipo_id', $this->equipoOrigenId)
                ->where('activo', true)
                ->pluck('usuario_id')->all();

            $pendientesOrigen = $miembrosOrigenIds === []
                ? 0
                : (int) DB::table('asignaciones')
                    ->where('proyecto_id', $proyectoId)
                    ->where('estado', 'pendiente')
                    ->whereIn('usuario_id', $miembrosOrigenIds)
                    ->count();
        }

        if ($this->equipoDestinoId !== null) {
            $miembrosDestino = (int) DB::table('equipo_usuario')
                ->where('proyecto_id', $proyectoId)
                ->where('equipo_id', $this->equipoDestinoId)
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

        return view('asignaciones::livewire.reasignar-entre-equipos', [
            'equipos' => $equipos,
            'pendientesOrigen' => $pendientesOrigen,
            'miembrosDestino' => $miembrosDestino,
            'usuariosDistribucion' => $usuariosDistribucion,
        ]);
    }
}
