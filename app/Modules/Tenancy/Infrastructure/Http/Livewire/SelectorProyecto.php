<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

final class SelectorProyecto extends Component
{
    /**
     * Filtro opcional por mandante (query param ?mandante=ID). Usado por
     * handshake F37 cuando JWT no trae proyecto_id: aterriza al usuario
     * acá para que elija proyecto dentro de su mandante.
     */
    #[Url(as: 'mandante')]
    public ?int $mandanteId = null;

    /**
     * Si el usuario tiene exactamente 1 proyecto activo (filtrado por mandante
     * si se pidió) y NO es ADMIN_GLOBAL, redirige al dashboard del proyecto
     * sin mostrar selector. ADMIN_GLOBAL siempre ve el listado completo.
     */
    public function mount(): RedirectResponse|Redirector|null
    {
        $usuario = auth()->user();
        if ($usuario === null || ($usuario->esAdminGlobal() ?? false)) {
            return null;
        }

        $proyectosIds = $this->resolverProyectosIdsAccesibles($usuario->id);

        if (count($proyectosIds) !== 1) {
            return null;
        }

        $proyectoId = (int) $proyectosIds[0];
        $existeActivo = DB::table('proyectos')
            ->where('id', $proyectoId)
            ->where('activo', true)
            ->whereNull('eliminada_en')
            ->exists();
        if (! $existeActivo) {
            return null;
        }

        return redirect()->route('proyectos.dashboard', ['proyecto_id' => $proyectoId]);
    }

    /**
     * F38: combina pivot proyecto (usuario_proyecto_rol) + pivot mandante
     * (usuario_mandante_rol). Si el user tiene rol ADMIN_MANDANTE, ve todos
     * los proyectos del mandante aunque no tenga pivot proyecto.
     *
     * @return list<int>
     */
    private function resolverProyectosIdsAccesibles(int $usuarioId): array
    {
        $idsProyecto = DB::table('usuario_proyecto_rol as upr')
            ->where('upr.usuario_id', $usuarioId)
            ->where('upr.activo', true)
            ->when(
                $this->mandanteId !== null,
                fn ($q) => $q->join('proyectos as p', 'p.id', '=', 'upr.proyecto_id')
                    ->where('p.mandante_id', $this->mandanteId),
            )
            ->pluck('upr.proyecto_id')
            ->map(fn (mixed $v): int => (int) $v)
            ->all();

        $idsMandante = DB::table('usuario_mandante_rol as umr')
            ->join('proyectos as p', 'p.mandante_id', '=', 'umr.mandante_id')
            ->where('umr.usuario_id', $usuarioId)
            ->where('umr.activo', true)
            ->where('p.activo', true)
            ->whereNull('p.eliminada_en')
            ->when(
                $this->mandanteId !== null,
                fn ($q) => $q->where('umr.mandante_id', $this->mandanteId),
            )
            ->pluck('p.id')
            ->map(fn (mixed $v): int => (int) $v)
            ->all();

        return array_values(array_unique([...$idsProyecto, ...$idsMandante]));
    }

    public function render(): View
    {
        $usuario = auth()->user();
        $esAdminGlobal = $usuario?->esAdminGlobal() ?? false;

        $query = DB::table('proyectos as p')
            ->join('mandantes as m', 'm.id', '=', 'p.mandante_id')
            ->whereNull('p.eliminada_en')
            ->where('p.activo', true)
            ->whereNull('m.eliminada_en')
            ->select([
                'p.id',
                'p.public_id',
                'p.codigo',
                'p.nombre',
                'p.tipo_operacion',
                'm.codigo as mandante_codigo',
                'm.nombre as mandante_nombre',
            ])
            ->orderBy('m.nombre')
            ->orderBy('p.nombre');

        if ($this->mandanteId !== null) {
            $query->where('p.mandante_id', $this->mandanteId);
        }

        if (! $esAdminGlobal) {
            $proyectosIds = $this->resolverProyectosIdsAccesibles((int) $usuario->id);

            if ($proyectosIds === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('p.id', $proyectosIds);
            }
        }

        $proyectos = $query->get();

        return view('tenancy::selector-proyecto', [
            'proyectos' => $proyectos,
            'esAdminGlobal' => $esAdminGlobal,
        ]);
    }
}
