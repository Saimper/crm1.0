<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Infrastructure\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
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
    /**
     * #[Locked] no es opcional aquí: sin él, el id que decide QUÉ proyecto se
     * mide llega del cliente, y bastaría con cambiarlo en el payload de
     * /livewire/update para leer las métricas de otra empresa. El middleware
     * `proyecto.activo` valida el proyecto de la URL, no esta propiedad.
     */
    #[Locked]
    public int $proyectoId;

    /** hoy | semana | mes */
    public string $rango = 'hoy';

    public function mount(int $proyectoId): void
    {
        // Y además se contrasta contra el proyecto que el middleware ya validó:
        // esa es la única fuente en la que se puede confiar.
        $activo = app()->bound('tenancy.proyecto_activo')
            ? (int) app('tenancy.proyecto_activo')->id
            : null;

        if ($activo === null || $activo !== $proyectoId) {
            abort(403, 'El panel no corresponde al proyecto activo.');
        }

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

        // Efectividad: qué proporción de lo gestionado acabó en contacto útil.
        // La bandera vive en el catálogo de resultados de cada proyecto.
        $efectivos = DB::table('resultados')
            ->where('proyecto_id', $this->proyectoId)
            ->where('es_contacto_efectivo', true)
            ->pluck('id')
            ->all();

        $gestionesEfectivas = $efectivos === [] ? 0 : (int) (clone $gestiones)
            ->whereIn('resultado_id', $efectivos)
            ->count();

        $totalGestiones = (int) (clone $gestiones)->count();

        // Dinero. OJO: el sistema NO registra pagos, solo la promesa y su estado,
        // así que esto es «prometido» y «cumplido», no «recuperado». Llamarlo
        // recuperado sería afirmar un cobro que la base no conoce.
        //
        // Las dos cifras salen de consultas distintas porque son poblaciones
        // distintas, y meterlas en una sola era el fallo: lo prometido se cuenta
        // por cuándo NACIÓ la promesa, y lo cumplido por cuándo SE RESOLVIÓ.
        // Sumando ambas con `c.creada_en >= $desde` sólo entraban las promesas
        // creadas Y cumplidas dentro del mismo rango, y una promesa creada hoy
        // casi nunca vence hoy: el importe cumplido daba 0,00 todos los días
        // mientras la tarjeta de al lado contaba tres promesas cumplidas.
        // Medido el día que se detectó: el panel decía 0,00 y lo cobrado eran
        // 3.572,91.
        $prometido = $this->montoPorMoneda(
            fn ($q) => $q->where('c.creada_en', '>=', $desde)
        );

        $cumplido = $this->montoPorMoneda(
            fn ($q) => $q->where('c.estado', 'cumplido')->where(
                fn ($w) => $this->resueltoEnRango($w, $desde)
            )
        );

        $roto = $this->montoPorMoneda(
            fn ($q) => $q->where('c.estado', 'roto')->where(
                fn ($w) => $this->resueltoEnRango($w, $desde)
            )
        );

        $dinero = ($prometido === null && $cumplido === null) ? null : (object) [
            'moneda' => $prometido->moneda ?? $cumplido->moneda ?? 'USD',
            'prometido' => (float) ($prometido->monto ?? 0),
            'cumplido' => (float) ($cumplido->monto ?? 0),
            'roto' => (float) ($roto->monto ?? 0),
        ];

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

        // Tendencia: un punto por día. Con el rango en «hoy» sería un solo punto,
        // que no es una tendencia — ahí no se dibuja.
        $tendencia = $this->rango === 'hoy' ? collect() : $this->gestionesPorDia($desde);

        // La portada del proyecto no pide permiso —todo el que trabaja ahí tiene
        // que poder entrar—, así que este panel es el que tiene que decidir qué
        // enseña. El ranking de compañeros y el dinero del proyecto son informe
        // de supervisión: sin `reportes.operativos` no se pintan. El resto (lo
        // que ha pasado hoy en el proyecto) sí, que es para lo que se entra.
        $puedeVerSupervision = auth()->user()?->tienePermiso('reportes.operativos', $this->proyectoId) === true;

        return view('reportes::livewire.panel-del-dia', [
            'puedeVerSupervision' => $puedeVerSupervision,
            'efectividad' => $totalGestiones > 0
                ? (int) round($gestionesEfectivas * 100 / $totalGestiones)
                : null,
            'gestionesEfectivas' => $gestionesEfectivas,
            'dinero' => $puedeVerSupervision ? $dinero : null,
            'tendencia' => $tendencia,
            'maximoDia' => (int) ($tendencia->max('total') ?? 0),
            'totalGestiones' => $totalGestiones,
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

    /**
     * Gestiones por día, con los días sin actividad incluidos en cero.
     *
     * Si se omitieran, dos días separados por una semana muerta se dibujarían
     * juntos y la caída no se vería: el hueco ES el dato.
     *
     * @return Collection<int, object>
     */
    private function gestionesPorDia(Carbon $desde): Collection
    {
        $conteos = DB::table('gestiones')
            ->where('proyecto_id', $this->proyectoId)
            ->whereNull('eliminada_en')
            ->where('creada_en', '>=', $desde)
            ->selectRaw('date(creada_en) as dia, count(*) as total')
            ->groupBy('dia')
            ->pluck('total', 'dia');

        $dias = collect();

        for ($d = $desde->copy(); $d->lte(Carbon::today()); $d->addDay()) {
            $clave = $d->toDateString();
            $dias->push((object) [
                'dia' => $clave,
                'etiqueta' => $d->translatedFormat('D j'),
                'total' => (int) ($conteos[$clave] ?? 0),
            ]);
        }

        return $dias;
    }

    /**
     * Importe de promesas de pago del proyecto, con el recorte que le pase quien
     * llama. Devuelve la moneda con más volumen; el panel no mezcla divisas.
     *
     * @param  \Closure(Builder): mixed  $recorte
     */
    private function montoPorMoneda(\Closure $recorte): ?object
    {
        $q = DB::table('compromisos as c')
            ->join('compromisos_promesa_pago as pp', 'pp.compromiso_id', '=', 'c.id')
            ->where('c.proyecto_id', $this->proyectoId)
            ->whereNull('c.eliminada_en');

        $recorte($q);

        return $q->selectRaw('pp.moneda, sum(pp.monto) as monto')
            ->groupBy('pp.moneda')
            ->orderByDesc('monto')
            ->first();
    }

    /**
     * El mismo criterio de «resuelto dentro del rango» que usa el contador de
     * promesas, para que el importe y el número no cuenten poblaciones distintas.
     */
    private function resueltoEnRango(mixed $q, Carbon $desde): void
    {
        $q->whereDate('c.fecha_resolucion', '>=', $desde->toDateString())
            ->orWhere(function ($q2) use ($desde): void {
                // fecha_resolucion es nullable en filas antiguas; para esas se
                // cae a cuándo se actualizó, igual que en compromisosResueltos().
                $q2->whereNull('c.fecha_resolucion')
                    ->where('c.actualizada_en', '>=', $desde);
            });
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
