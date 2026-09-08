<?php

declare(strict_types=1);

namespace App\Modules\Compromisos\Application\Console\Commands;

use App\Modules\Compromisos\Application\DTOs\ResolverCompromisoInput;
use App\Modules\Compromisos\Application\UseCases\MarcarCompromisoRoto;
use App\Modules\Compromisos\Infrastructure\Persistence\Models\CompromisoModel;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
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

    public function handle(MarcarCompromisoRoto $romper): int
    {
        $hoy = Carbon::today();
        $simulacro = (bool) $this->option('dry-run');
        $proyecto = $this->option('proyecto') !== null ? (int) $this->option('proyecto') : null;

        // `sinScopeProyecto()` explícito y no por omisión: fuera de una petición
        // HTTP no hay proyecto activo y el scope no filtraría igual, pero eso
        // sería depender del fallo abierto. Aquí recorrer todos los proyectos es
        // la intención, y se escribe (§10).
        $consulta = CompromisoModel::query()
            ->sinScopeProyecto()
            ->where('estado', 'pendiente')
            ->whereNull('eliminada_en')
            ->whereDate('fecha_vencimiento', '<', $hoy->toDateString())
            ->when($proyecto !== null, fn ($q) => $q->where('proyecto_id', $proyecto))
            ->orderBy('id');

        $total = (clone $consulta)->count();

        if ($total === 0) {
            $this->info('No hay compromisos vencidos sin resolver.');

            return self::SUCCESS;
        }

        if ($simulacro) {
            $this->warn("Simulacro: se romperían {$total} compromisos.");
            foreach ((clone $consulta)->limit(20)->get(['id', 'proyecto_id', 'tipo_compromiso', 'fecha_vencimiento']) as $c) {
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
        $ids = (clone $consulta)->pluck('id', 'id');
        $vencimientos = (clone $consulta)->pluck('fecha_vencimiento', 'id');

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
}
