<?php

declare(strict_types=1);

namespace App\Modules\Notificaciones\Application\Console\Commands;

use App\Modules\Notificaciones\Application\Services\GeneradorNotificaciones;
use Illuminate\Console\Command;

final class GenerarNotificacionesCommand extends Command
{
    protected $signature = 'notificaciones:generar
        {--umbral=3 : Días antes del vencimiento del compromiso que dispara la alerta}
        {--horas-sla=8 : Horas antes del SLA de un ticket CX que dispara la alerta}';

    protected $description = 'Genera notificaciones de compromisos por vencer/vencidos y SLA CX en riesgo. Idempotente.';

    public function handle(GeneradorNotificaciones $generador): int
    {
        $umbral = (int) $this->option('umbral');
        $horasSla = (int) $this->option('horas-sla');
        $creadas = $generador->ejecutar(umbralDias: $umbral, umbralHorasSla: $horasSla);

        $this->info("Notificaciones creadas: {$creadas} (umbral {$umbral} días, SLA {$horasSla}h).");

        return self::SUCCESS;
    }
}
