<?php

declare(strict_types=1);

namespace App\Modules\Compromisos\Application\Console\Commands;

use App\Modules\Compromisos\Application\DTOs\ResolverCompromisoInput;
use App\Modules\Compromisos\Application\UseCases\MarcarCompromisoRoto;
use App\Modules\Compromisos\Infrastructure\Persistence\Models\CompromisoModel;
use App\Modules\Tenancy\Application\Services\RelojDelMandante;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Cierra los compromisos cuya fecha de vencimiento ya pasó y siguen pendientes.
 *
 * Es la pieza que faltaba del ciclo de §6. Había UseCases para las tres
 * transiciones y una pantalla para dispararlas a mano, pero nada convertía el
 * paso del tiempo en un hecho: una promesa vencida se quedaba `pendiente` para
 * siempre, la bandera `tiene_compromiso_vigente` del caso seguía encendida, y el
 * gestor abría la cuenta y leía «compromiso vigente» sobre algo que no lo era.
 *
 * SIN PERIODO DE GRACIA, por decisión de negocio: si vence, se rompe. Lo que
 * sigue después es llamar al deudor para saber por qué, y de eso se encarga la
 * notificación que dispara `CompromisoRoto`.
 *
 * La fecha de resolución es el día siguiente al vencimiento, no «hoy»: el
 * compromiso se rompió cuando pasó su fecha, no cuando el cron se enteró. Si la
 * tarea no corre durante una semana, las promesas rotas conservan la fecha en la
 * que de verdad se rompieron y los informes por período no se descuadran.
 *
 * Idempotente: sólo mira `pendiente`, así que volver a correrlo no hace nada.
 */
final class RomperCompromisosVencidosCommand extends Command
{
    protected $signature = 'compromisos:romper-vencidos
        {--dry-run : Enumera lo que rompería sin tocar nada}
        {--proyecto= : Limita a un proyecto concreto}
        {--chunk=200 : Compromisos por lote}';

    protected $description = 'Marca como rotos los compromisos vencidos que siguen pendientes. Sin periodo de gracia.';

    public function handle(MarcarCompromisoRoto $romper, RelojDelMandante $reloj): int
    {
        $simulacro = (bool) $this->option('dry-run');
        $proyecto = $this->option('proyecto') !== null ? (int) $this->option('proyecto') : null;

        // `sinScopeProyecto()` explícito y no por omisión: fuera de una petición
        // HTTP no hay proyecto activo y el scope no filtraría igual, pero eso
        // sería depender del fallo abierto. Aquí recorrer todos los proyectos es
        // la intención, y se escribe (§10).
        // El corte del día lo pone el calendario de CADA cliente, no el del
        // servidor. Con la operación en Panamá y el corte en UTC, una promesa
        // que vence el día 7 se rompía a las 19:00 hora local del 7, cinco horas
        // antes de que al deudor se le acabara el plazo.
        //
        // La tarea recorre todos los mandantes, así que se agrupa por el suyo:
        // cada uno se compara con su propio «hoy».
        $consulta = CompromisoModel::query()
            ->sinScopeProyecto()
            ->where('compromisos.estado', 'pendiente')
            ->whereNull('compromisos.eliminada_en')
            ->join('proyectos', 'proyectos.id', '=', 'compromisos.proyecto_id')
            ->join('casos as scheduled_case', 'scheduled_case.id', '=', 'compromisos.caso_id')
            ->join('carteras as scheduled_portfolio', 'scheduled_portfolio.id', '=', 'scheduled_case.cartera_id')
            ->join('personas as scheduled_person', 'scheduled_person.id', '=', 'scheduled_case.persona_id')
            ->join('mandantes as scheduled_client', 'scheduled_client.id', '=', 'proyectos.mandante_id')
            ->whereNull('scheduled_case.eliminada_en')->whereNull('scheduled_person.eliminada_en')
            ->where('scheduled_portfolio.activo', true)->whereNull('scheduled_portfolio.eliminada_en')
            ->where('proyectos.activo', true)->whereNull('proyectos.eliminada_en')
            ->where('scheduled_client.activo', true)->whereNull('scheduled_client.eliminada_en')
            ->where(function ($q) use ($reloj): void {
                foreach ($this->proyectosConSuHoy($reloj) as $projectId => $hoyLocal) {
                    $q->orWhere(fn ($w) => $w
                        ->where('compromisos.proyecto_id', $projectId)
                        ->whereDate('compromisos.fecha_vencimiento', '<', $hoyLocal));
                }
            })
            ->when($proyecto !== null, fn ($q) => $q->where('compromisos.proyecto_id', $proyecto))
            ->select('compromisos.*')
            ->orderBy('compromisos.id');

        $total = (clone $consulta)->count();

        if ($total === 0) {
            $this->info('No hay compromisos vencidos sin resolver.');

            return self::SUCCESS;
        }

        if ($simulacro) {
            $this->warn("Simulacro: se romperían {$total} compromisos.");
            foreach ((clone $consulta)->limit(20)->get(['compromisos.id', 'compromisos.proyecto_id', 'compromisos.tipo_compromiso', 'compromisos.fecha_vencimiento']) as $c) {
                $this->line(sprintf(
                    '  #%d  proyecto %d  %s  venció %s',
                    $c->id, $c->proyecto_id, $c->tipo_compromiso, $c->fecha_vencimiento
                ));
            }
            if ($total > 20) {
                $this->line('  … y '.($total - 20).' más.');
            }

            return self::SUCCESS;
        }

        $rotos = 0;
        $fallidos = 0;

        // Los ids se toman de una vez: el UseCase cambia el estado a `roto` y eso
        // saca la fila del criterio de la consulta, así que paginar sobre ella
        // mientras se modifica se saltaría lotes enteros.
        $ids = (clone $consulta)->pluck('compromisos.id', 'compromisos.id');
        $vencimientos = (clone $consulta)->pluck('compromisos.fecha_vencimiento', 'compromisos.id');

        foreach ($ids->chunk((int) $this->option('chunk')) as $lote) {
            foreach ($lote as $id) {
                $vencimiento = new DateTimeImmutable((string) $vencimientos[$id]);

                try {
                    $romper->execute(new ResolverCompromisoInput(
                        compromisoId: (int) $id,
                        fechaResolucion: $vencimiento->modify('+1 day'),
                    ));
                    $rotos++;
                } catch (Throwable $e) {
                    $fallidos++;
                    $this->error("Compromiso {$id}: ".$e->getMessage());
                }
            }
        }

        $this->info("Compromisos rotos: {$rotos}".($fallidos > 0 ? " · fallidos: {$fallidos}" : ''));

        return $fallidos > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * El «hoy» de cada mandante, en su propio calendario.
     *
     * @return array<int, string> mandante_id => 'Y-m-d'
     */
    private function proyectosConSuHoy(RelojDelMandante $reloj): array
    {
        $hoyPorMandante = [];

        foreach (DB::table('proyectos')->pluck('id') as $projectId) {
            $hoyPorMandante[(int) $projectId] = $reloj->hoy(proyectoId: (int) $projectId);
        }

        return $hoyPorMandante;
    }
}
