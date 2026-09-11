<?php

declare(strict_types=1);

namespace App\Modules\Asignaciones\Infrastructure\Http\Livewire;

use App\Modules\Asignaciones\Application\UseCases\AsignarCuentasSinDueno;
use App\Modules\Usuarios\Domain\Contracts\AccesoAReparto;
use App\Support\Database\CarterasOperativas;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/** Assigns a bounded batch to an advisor or an optional team. */
final class AsignarMasivamente extends Component
{
    public ?int $equipoId = null;

    public string $destino = 'asesor';

    public ?int $asesorId = null;

    public ?int $carteraId = null;

    public int $limite = 150;

    #[Locked]
    public int $ultAsignadas = 0;

    #[Locked]
    public int $ultOmitidas = 0;

    /** @var array<int, int> usuarioId => cantidad */
    #[Locked]
    public array $ultDistribucion = [];

    public function updatedEquipoId(): void
    {
        if ($this->equipoId !== null) {
            $this->destino = 'equipo';
        }
    }

    public function asignar(AsignarCuentasSinDueno $useCase): void
    {
        abort_unless(auth()->user()?->tienePermiso('asignaciones.reasignar') === true, 403);

        $this->validate([
            'destino' => ['in:asesor,equipo'],
            'equipoId' => ['nullable', 'required_if:destino,equipo', 'integer', 'min:1'],
            'asesorId' => ['nullable', 'required_if:destino,asesor', 'integer', 'min:1'],
            'carteraId' => ['nullable', 'integer', 'min:1'],
            'limite' => ['integer', 'min:0'],
        ]);

        $proyectoId = (int) app('tenancy.proyecto_activo')->id;

        try {
            $r = $useCase->execute(
                proyectoId: $proyectoId,
                actorId: (int) auth()->id(),
                asesorId: $this->destino === 'asesor' ? $this->asesorId : null,
                equipoId: $this->destino === 'equipo' ? $this->equipoId : null,
                carteraId: $this->carteraId,
                limite: (int) $this->limite,
            );
        } catch (Throwable $e) {
            $this->addError('reparto', $e->getMessage());

            return;
        }

        $this->ultAsignadas = $r->asignadas;
        $this->ultOmitidas = $r->omitidas;
        $this->ultDistribucion = $r->distribucion;

        session()->flash('asignacion-masiva-ok', "{$r->asignadas} cuentas asignadas, {$r->omitidas} omitidas.");
    }

    public function render(): View
    {
        $proyectoId = (int) app('tenancy.proyecto_activo')->id;
        $acceso = app(AccesoAReparto::class);
        abort_unless($acceso->puedeRepartir((int) auth()->id(), $proyectoId), 403);
        $carteras = DB::table('carteras')->where('proyecto_id', $proyectoId)->where('activo', true)->whereNull('eliminada_en')
            ->orderBy('nombre')->get(['id', 'nombre'])
            ->filter(fn ($cartera): bool => $acceso->puedeRepartir((int) auth()->id(), $proyectoId, (int) $cartera->id));
        $carterasVisibles = $carteras->pluck('id')->all();
        $asesores = DB::table('users as u')->where('u.activo', true)
            ->where(fn ($q) => $q->whereExists(fn ($r) => $r->selectRaw('1')->from('usuario_proyecto_rol as upr')
                ->whereColumn('upr.usuario_id', 'u.id')->where('upr.proyecto_id', $proyectoId)->where('upr.activo', true))
                ->orWhereExists(fn ($r) => $r->selectRaw('1')->from('usuario_proyecto_rol_custom as uprc')
                    ->join('roles_custom as rc', 'rc.id', '=', 'uprc.rol_custom_id')
                    ->whereColumn('uprc.usuario_id', 'u.id')->whereColumn('rc.proyecto_id', 'uprc.proyecto_id')
                    ->where('uprc.proyecto_id', $proyectoId)->where('uprc.activo', true)->where('rc.activo', true)->whereNull('rc.eliminada_en')))
            ->orderBy('u.name')->get(['u.id', 'u.name'])
            ->filter(function ($usuario) use ($acceso, $proyectoId, $carterasVisibles): bool {
                foreach ($carterasVisibles as $id) {
                    if (($this->carteraId === null || $this->carteraId === (int) $id)
                        && $acceso->puedeRecibir((int) $usuario->id, $proyectoId, (int) $id)) {
                        return true;
                    }
                }

                return false;
            });

        $equipos = DB::table('equipos')
            ->where('proyecto_id', $proyectoId)
            ->where('activo', true)
            ->whereNull('eliminada_en')
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre']);

        // Cuántas cuentas hay para repartir. Ya no depende de elegir nada
        // antes: es el mismo criterio de elegibilidad del UseCase.
        $casosSinAsignar = (int) DB::table('casos as c')
            ->join('personas as pe', fn ($join) => $join->on('pe.id', '=', 'c.persona_id')->on('pe.proyecto_id', '=', 'c.proyecto_id'))
            ->whereNull('pe.eliminada_en')
            ->whereIn('c.cartera_id', $carterasVisibles)
            ->when($this->carteraId !== null, fn ($q) => $q->where('c.cartera_id', $this->carteraId))
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('asignaciones as a')
                ->whereColumn('a.caso_id', 'c.id')
                ->where('a.proyecto_id', $proyectoId))
            ->where('c.proyecto_id', $proyectoId)
            ->whereNull('c.cerrado_en')
            ->whereNull('c.eliminada_en')
            ->where(fn ($q) => CarterasOperativas::filtrar($q))
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
            'asesores' => $asesores,
            'carteras' => $carteras,
            'casosSinAsignar' => $casosSinAsignar,
            'miembrosActivos' => $miembrosActivos,
            'usuariosDistribucion' => $usuariosDistribucion,
        ]);
    }
}
