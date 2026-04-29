<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE notificaciones MODIFY COLUMN tipo ENUM('compromiso_por_vencer','compromiso_vencido','sla_en_riesgo','compromiso_resuelto','asignacion_recibida') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE notificaciones MODIFY COLUMN tipo ENUM('compromiso_por_vencer','compromiso_vencido','sla_en_riesgo','compromiso_resuelto') NOT NULL");
    }
};
