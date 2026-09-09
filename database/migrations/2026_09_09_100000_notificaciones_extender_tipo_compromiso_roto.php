<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tipo nuevo `compromiso_roto`.
 *
 * Hasta ahora el aviso operativo era `compromiso_vencido`, que se generaba
 * mientras la promesa seguía pendiente después de su fecha. Ese estado deja de
 * existir en cuanto `compromisos:romper-vencidos` cierra el ciclo, así que sin
 * un tipo nuevo la ruptura se quedaría muda: exactamente el momento en que hay
 * que llamar al deudor para saber por qué no pagó.
 *
 * `compromiso_vencido` se conserva: sigue habiendo una ventana entre que la
 * promesa vence y la tarea diaria corre, y las notificaciones ya emitidas no se
 * reescriben.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE notificaciones MODIFY COLUMN tipo ENUM('compromiso_por_vencer','compromiso_vencido','compromiso_roto','sla_en_riesgo','compromiso_resuelto','asignacion_recibida') NOT NULL");
    }

    public function down(): void
    {
        DB::table('notificaciones')->where('tipo', 'compromiso_roto')->delete();

        DB::statement("ALTER TABLE notificaciones MODIFY COLUMN tipo ENUM('compromiso_por_vencer','compromiso_vencido','sla_en_riesgo','compromiso_resuelto','asignacion_recibida') NOT NULL");
    }
};
