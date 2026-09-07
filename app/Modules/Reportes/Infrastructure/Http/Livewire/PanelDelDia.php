<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Infrastructure\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Cómo va la operación, de un vistazo, en la portada del proyecto.
 *
 * La portada era una rejilla de enlaces: para saber si el día iba bien había que
 * entrar a Reportes operativos. Los números que importan a diario —cuánto se
 * gestionó, quién, y qué pasa con las promesas— viven ahora donde se entra.
 *
 * Los cálculos están aquí y no en la vista (§13.4). El informe completo sigue en
 * Reportes operativos; esto es el resumen, no su sustituto.
 */
final class PanelDelDia extends Component
{
    public int $proyectoId;

    /** hoy | semana | mes */
    public string $rango = 'hoy';

    public function mount(int $proyectoId): void
    {
        $this->proyectoId = $proyectoId;
    }

    public function cambiarRango(string $rango): void
    {
        $this->rango = in_array($rango, ['hoy', 'semana', 'mes'], true) ? $rango : 'hoy';
    }

    public function render(): View
    {
        $desde = $this->desde();
        $hoy = Carbon::today();

        $gestiones = DB::table('gestiones')
            ->where('proyecto_id', $this->proyectoId)
            ->whereNull('eliminada_en')
            ->where('creada_en', '>=', $desde);

        // «A la fecha»: lo que sigue vivo hoy, no lo que se creó en el rango.
        // Una promesa vigente lo es ahora mismo, independientemente del filtro.
        $vigentes = (int) DB::table('compromisos')
            ->where('proyecto_id', $this->proyectoId)
            ->where('estado', 'pendiente')
            ->whereNull('eliminada_en')
            ->whereDate('fecha_vencimiento', '>=', $hoy)
            ->count();

        $cumplidas = (int) $this->compromisosResueltos('cumplido', $desde);
        $rotas = (int) $this->compromisosResueltos('roto', $desde);

        // Vencidas y sin resolver: ni cumplidas ni marcadas rotas todavía. Es la
        // cifra que avisa de trabajo pendiente, y la que se pierde si solo se
        // miran los tres estados.
        $vencidasSinResolver = (int) DB::table('compromisos')
            ->where('proyecto_id', $this->proyectoId)
            ->where('estado', 'pendiente')
            ->whereNull('eliminada_en')
            ->whereDate('fecha_vencimiento', '<', $hoy)
            ->count();

        $porUsuario = DB::table('gestiones as g')
            ->join('users as u', 'u.id', '=', 'g.usuario_id')
            ->where('g.proyecto_id', $this->proyectoId)
            ->whereNull('g.eliminada_en')
            ->where('g.creada_en', '>=', $desde)
            ->select(['u.id', 'u.name', DB::raw('count(*) as total')])
            ->groupBy('u.id', 'u.name')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        return view('reportes::livewire.panel-del-dia', [
            'totalGestiones' => (int) $gestiones->count(),
            'vigentes' => $vigentes,
            'cumplidas' => $cumplidas,
            'rotas' => $rotas,
            'vencidasSinResolver' => $vencidasSinResolver,
            'porUsuario' => $porUsuario,
            // El máximo da la escala de las barras. Sin él, una barra sola
            // ocuparía todo el ancho y parecería un récord.
            'maximo' => (int) ($porUsuario->max('total') ?? 0),
            'etiquetaRango' => __('reportes.panel_rango_'.$this->rango),
        ]);
    }

    private function compromisosResueltos(string $estado, Carbon $desde): int
    {
        return DB::table('compromisos')
            ->where('proyecto_id', $this->proyectoId)
            ->where('estado', $estado)
            ->whereNull('eliminada_en')
            ->where(function ($q) use ($desde): void {
                // fecha_resolucion es lo correcto, pero es nullable en filas
                // antiguas: para esas se cae a cuándo se actualizó la promesa.
                $q->whereDate('fecha_resolucion', '>=', $desde->toDateString())
                    ->orWhere(function ($q2) use ($desde): void {
                        $q2->whereNull('fecha_resolucion')
                            ->where('actualizada_en', '>=', $desde);
                    });
            })
            ->count();
    }

    private function desde(): Carbon
    {
        return match ($this->rango) {
            'semana' => Carbon::today()->subDays(6),
            'mes' => Carbon::today()->subDays(29),
            default => Carbon::today(),
        };
    }
}
