<?php

declare(strict_types=1);

namespace App\Modules\Personas\Infrastructure\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Buscador global scoped al proyecto activo (§9 CLAUDE.md v2).
 * Busca personas y casos por identificación o nombre dentro del proyecto actual.
 * Usa Ctrl+K para abrir; mínimo 3 caracteres para buscar.
 */
final class BuscadorGlobal extends Component
{
    public bool $abierto = false;

    public string $query = '';

    public function abrir(): void
    {
        $this->abierto = true;
    }

    public function cerrar(): void
    {
        $this->abierto = false;
        $this->query   = '';
    }

    public function render(): View
    {
        $texto          = trim($this->query);
        $proyectoActivo = app()->bound('tenancy.proyecto_activo')
            ? app('tenancy.proyecto_activo')
            : null;

        $personas = collect();
        $casos    = collect();

        if ($proyectoActivo !== null && mb_strlen($texto) >= 3) {
            $proyectoId = (int) $proyectoActivo->id;
            $like       = "%{$texto}%";

            $personas = DB::table('personas as p')
                ->leftJoin('tipos_identificacion as ti', 'ti.id', '=', 'p.tipo_identificacion_id')
                ->where('p.proyecto_id', $proyectoId)
                ->whereNull('p.eliminada_en')
                ->where(function ($w) use ($like): void {
                    $w->where('p.identificacion', 'like', $like)
                        ->orWhere('p.nombres',      'like', $like)
                        ->orWhere('p.apellidos',    'like', $like)
                        ->orWhere('p.razon_social', 'like', $like);
                })
                ->select([
                    'p.id', 'p.public_id', 'p.tipo_persona',
                    'p.identificacion', 'p.nombres', 'p.apellidos', 'p.razon_social',
                    'ti.codigo as tipo_identificacion_codigo',
                ])
                ->limit(8)
                ->get();

            $casos = DB::table('casos as c')
                ->join('personas as p',          'p.id',  '=', 'c.persona_id')
                ->leftJoin('carteras as ca',     'ca.id', '=', 'c.cartera_id')
                ->leftJoin('estados_caso as ec', 'ec.id', '=', 'c.estado_caso_id')
                ->where('c.proyecto_id', $proyectoId)
                ->whereNull('c.eliminada_en')
                ->where(function ($w) use ($like): void {
                    $w->where('p.identificacion', 'like', $like)
                        ->orWhere('p.nombres',      'like', $like)
                        ->orWhere('p.apellidos',    'like', $like)
                        ->orWhere('p.razon_social', 'like', $like);
                })
                ->select([
                    'c.public_id as caso_public_id',
                    'c.tipo_caso',
                    'p.public_id as persona_public_id',
                    'p.identificacion', 'p.tipo_persona',
                    'p.nombres', 'p.apellidos', 'p.razon_social',
                    'ca.nombre as cartera_nombre',
                    'ec.nombre as estado_caso_nombre',
                ])
                ->orderByDesc('c.prioridad')
                ->limit(8)
                ->get();
        }

        return view('personas::livewire.buscador-global', [
            'personas'       => $personas,
            'casos'          => $casos,
            'proyectoActivo' => $proyectoActivo,
        ]);
    }
}
