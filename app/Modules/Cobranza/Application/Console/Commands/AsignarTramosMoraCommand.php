<?php

declare(strict_types=1);

namespace App\Modules\Cobranza\Application\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Clasifica cada caso de cobranza en su tramo de mora a partir de `dias_mora`.
 *
 * `casos_cobranza.tramo_mora_id` existía desde el principio: el repositorio lo
 * persiste, la Vista de Trabajo lo muestra y el configurador administra el
 * catálogo — pero nada lo calculaba nunca, así que la columna estaba al 100%
 * en NULL y la cartera no se podía segmentar por mora.
 *
 * Es idempotente: recalcula siempre desde `dias_mora`, de modo que correrlo dos
 * veces seguidas no cambia nada y correrlo tras una importación reclasifica lo
 * que haya cambiado. Por eso también va en el scheduler.
 *
 * Un caso sin `dias_mora` se queda sin tramo: no se inventa una clasificación
 * sobre un dato que no existe.
 */
final class AsignarTramosMoraCommand extends Command
{
    protected $signature = 'cobranza:asignar-tramos-mora
                            {--proyecto= : ID del proyecto (por defecto, todos)}
                            {--dry-run : Solo informa cuántos casos cambiarían de tramo}';

    protected $description = 'Clasifica los casos de cobranza en su tramo de mora según dias_mora';

    public function handle(): int
    {
        $seco = (bool) $this->option('dry-run');
        $proyecto = $this->option('proyecto');

        $proyectos = DB::table('tramos_mora')
            ->where('activo', true)
            ->when($proyecto !== null, fn ($q) => $q->where('proyecto_id', (int) $proyecto))
            ->distinct()
            ->pluck('proyecto_id');

        if ($proyectos->isEmpty()) {
            $this->warn('No hay ningún proyecto con tramos de mora activos.');

            return self::SUCCESS;
        }

        $total = 0;
        $sinTramo = 0;

        foreach ($proyectos as $proyectoId) {
            [$cambios, $huerfanos] = $this->procesarProyecto((int) $proyectoId, $seco);
            $total += $cambios;
            $sinTramo += $huerfanos;

            $this->line(sprintf(
                '  proyecto %-4d %s %d',
                $proyectoId,
                $seco ? 'reclasificaría:' : 'reclasificados: ',
                $cambios
            ));
        }

        if ($sinTramo > 0) {
            $this->warn(sprintf(
                '%d casos con días de mora fuera de todos los tramos definidos: revisa que los rangos cubran sin huecos.',
                $sinTramo
            ));
        }

        $this->info($seco
            ? "Simulación: {$total} casos cambiarían de tramo."
            : "Listo: {$total} casos reclasificados.");

        return self::SUCCESS;
    }

    /** @return array{0:int,1:int} [casos cambiados, casos sin tramo aplicable] */
    private function procesarProyecto(int $proyectoId, bool $seco): array
    {
        $tramos = DB::table('tramos_mora')
            ->where('proyecto_id', $proyectoId)
            ->where('activo', true)
            ->orderBy('dias_desde')
            ->get(['id', 'dias_desde', 'dias_hasta']);

        $cambios = 0;
        $sinTramo = 0;

        DB::table('casos_cobranza as cc')
            ->join('casos as k', 'k.id', '=', 'cc.caso_id')
            ->where('k.proyecto_id', $proyectoId)
            ->whereNotNull('cc.dias_mora')
            ->select(['cc.caso_id', 'cc.dias_mora', 'cc.tramo_mora_id'])
            ->orderBy('cc.caso_id')
            ->chunk(1000, function ($casos) use ($tramos, $seco, &$cambios, &$sinTramo): void {
                $porTramo = [];

                foreach ($casos as $caso) {
                    $tramoId = $this->tramoPara($tramos, (int) $caso->dias_mora);

                    if ($tramoId === null) {
                        $sinTramo++;

                        continue;
                    }

                    if ((int) ($caso->tramo_mora_id ?? 0) === $tramoId) {
                        continue;
                    }

                    $porTramo[$tramoId][] = (int) $caso->caso_id;
                    $cambios++;
                }

                if ($seco || $porTramo === []) {
                    return;
                }

                // Una sentencia por tramo en vez de una por caso: con carteras de
                // decenas de miles de filas la diferencia es de minutos a segundos.
                DB::transaction(function () use ($porTramo): void {
                    foreach ($porTramo as $tramoId => $casoIds) {
                        DB::table('casos_cobranza')
                            ->whereIn('caso_id', $casoIds)
                            ->update(['tramo_mora_id' => $tramoId]);
                    }
                });
            });

        return [$cambios, $sinTramo];
    }

    /**
     * `dias_hasta` nulo significa "sin tope", que es el último tramo de la escalera.
     *
     * @param  \Illuminate\Support\Collection<int, \stdClass>  $tramos
     */
    private function tramoPara($tramos, int $diasMora): ?int
    {
        foreach ($tramos as $tramo) {
            $desde = (int) $tramo->dias_desde;
            $hasta = $tramo->dias_hasta === null ? null : (int) $tramo->dias_hasta;

            if ($diasMora >= $desde && ($hasta === null || $diasMora <= $hasta)) {
                return (int) $tramo->id;
            }
        }

        return null;
    }
}
