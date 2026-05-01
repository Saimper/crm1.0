<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

final class SelectorProyecto extends Component
{
    /**
     * Si el usuario tiene exactamente 1 proyecto activo y NO es ADMIN_GLOBAL,
     * redirige al dashboard del proyecto sin mostrar selector. ADMIN_GLOBAL
     * siempre ve el listado completo.
     */
    public function mount(): ?RedirectResponse
    {
        $usuario = auth()->user();
        if ($usuario === null || ($usuario->esAdminGlobal() ?? false)) {
            return null;
        }

        $proyectosIds = DB::table('usuario_proyecto_rol')
            ->where('usuario_id', $usuario->id)
            ->where('activo', true)
            ->pluck('proyecto_id')
            ->all();

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

        if (! $esAdminGlobal) {
            $proyectosIds = DB::table('usuario_proyecto_rol')
                ->where('usuario_id', $usuario->id)
                ->where('activo', true)
                ->pluck('proyecto_id')
                ->all();

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
