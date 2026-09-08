<?php

declare(strict_types=1);

namespace App\Modules\Cobranza\Application\Console\Commands;

use App\Modules\Cobranza\Application\DTOs\AvanzarDiasMoraInput;
use App\Modules\Cobranza\Application\UseCases\AvanzarDiasMora;
use App\Modules\Cobranza\Domain\ValueObjects\DiasMora;
use Illuminate\Console\Command;

/**
 * Envejece los días de mora de las cuentas abiertas hasta el «hoy» de cada
 * cliente. Toda la regla vive en el UseCase; aquí sólo se informa.
 *
 * Va en el scheduler cada hora y es no-op cuando el ancla ya es hoy, así que
 * correrlo a mano después de un despliegue o de reactivar el cron es seguro.
 */
final class AvanzarDiasMoraCommand extends Command
{
    protected $signature = 'cobranza:avanzar-dias-mora
                            {--dry-run : Solo informa cuántas cuentas avanzarían}
                            {--proyecto= : ID del proyecto (por defecto, todos los de cobranza)}';

    protected $description = 'Envejece dias_mora de las cuentas abiertas hasta el día de hoy de cada cliente';

    public function handle(AvanzarDiasMora $avanzar): int
    {
        $simulacro = (bool) $this->option('dry-run');
        $proyecto = $this->option('proyecto');

        if ($proyecto !== null && ! ctype_digit((string) $proyecto)) {
            $this->error('--proyecto tiene que ser el id numérico de un proyecto.');

            return self::FAILURE;
        }

        $salida = $avanzar->execute(new AvanzarDiasMoraInput(
            proyectoId: $proyecto === null ? null : (int) $proyecto,
            simulacro: $simulacro,
        ));

        if ($salida->avanzadosPorProyecto === []) {
            $this->warn('No hay ningún proyecto de cobranza que envejecer.');

            return self::SUCCESS;
        }

        foreach ($salida->avanzadosPorProyecto as $proyectoId => $avanzadas) {
            $this->line(sprintf(
                '  proyecto %-4d %s %-6d en tope: %-5d sin fecha: %d',
                $proyectoId,
                $simulacro ? 'avanzarían:' : 'avanzadas: ',
                $avanzadas,
                $salida->enTopePorProyecto[$proyectoId] ?? 0,
                $salida->sinFechaPorProyecto[$proyectoId] ?? 0,
            ));
        }

        if ($salida->sinConfirmarHaceTiempo > 0) {
            $this->warn(sprintf(
                '%d cuentas llevan más de %d días sin que el cliente confirme su mora: si el cliente dejó de enviarlas, hay que cerrarlas.',
                $salida->sinConfirmarHaceTiempo,
                DiasMora::DIAS_SIN_CONFIRMAR_AVISO,
            ));
        }

        $total = $salida->totalAvanzados();

        $this->info($simulacro
            ? "Simulación: {$total} cuentas avanzarían."
            : "Listo: {$total} cuentas avanzadas.");

        return self::SUCCESS;
    }
}
