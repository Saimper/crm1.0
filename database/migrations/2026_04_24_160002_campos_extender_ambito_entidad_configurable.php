<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Extiende `campos_personalizados.ambito` con el valor `entidad_configurable` (Fase 24).
 * Cuando un campo tiene este ámbito, `ambito_id` referencia a `entidades_configurables.id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE campos_personalizados MODIFY COLUMN ambito ENUM('caso','gestion','compromiso','entidad_configurable') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE campos_personalizados MODIFY COLUMN ambito ENUM('caso','gestion','compromiso') NOT NULL");
    }
};
