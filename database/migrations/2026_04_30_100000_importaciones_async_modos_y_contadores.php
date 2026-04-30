<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F31: importaciones async con modos merge/skip_duplicados/overwrite.
 * - Renombra estados (borrador→pendiente, validada→preparada).
 * - Reemplaza contadores (filas_ok|filas_error|filas_importadas → procesadas|validas|invalidas|omitidas|duplicadas).
 * - Agrega: modo, iniciado_en, terminado_en, error_global.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE importaciones MODIFY COLUMN estado ENUM('borrador','pendiente','validada','preparada','procesando','completada','fallida','cancelada') NOT NULL DEFAULT 'pendiente'");
        DB::statement("UPDATE importaciones SET estado = 'pendiente' WHERE estado = 'borrador'");
        DB::statement("UPDATE importaciones SET estado = 'preparada' WHERE estado = 'validada'");
        DB::statement("ALTER TABLE importaciones MODIFY COLUMN estado ENUM('pendiente','preparada','procesando','completada','fallida','cancelada') NOT NULL DEFAULT 'pendiente'");

        Schema::table('importaciones', function (Blueprint $table): void {
            $table->enum('modo', ['merge', 'skip_duplicados', 'overwrite'])->default('merge')->after('tipo_entidad');
            $table->unsignedInteger('procesadas')->default(0)->after('total_filas');
            $table->unsignedInteger('validas')->default(0)->after('procesadas');
            $table->unsignedInteger('invalidas')->default(0)->after('validas');
            $table->unsignedInteger('omitidas')->default(0)->after('invalidas');
            $table->unsignedInteger('duplicadas')->default(0)->after('omitidas');
            $table->timestamp('iniciado_en')->nullable()->after('duplicadas');
            $table->timestamp('terminado_en')->nullable()->after('iniciado_en');
            $table->text('error_global')->nullable()->after('terminado_en');
        });

        DB::statement('UPDATE importaciones SET validas = filas_ok, invalidas = filas_error, procesadas = filas_importadas');

        Schema::table('importaciones', function (Blueprint $table): void {
            $table->dropColumn(['filas_ok', 'filas_error', 'filas_importadas']);
            $table->index(['proyecto_id', 'estado'], 'importaciones_proyecto_estado_simple_idx');
        });
    }

    public function down(): void
    {
        Schema::table('importaciones', function (Blueprint $table): void {
            $table->dropIndex('importaciones_proyecto_estado_simple_idx');
            $table->unsignedInteger('filas_ok')->default(0);
            $table->unsignedInteger('filas_error')->default(0);
            $table->unsignedInteger('filas_importadas')->default(0);
        });

        DB::statement('UPDATE importaciones SET filas_ok = validas, filas_error = invalidas, filas_importadas = procesadas');

        Schema::table('importaciones', function (Blueprint $table): void {
            $table->dropColumn(['modo', 'procesadas', 'validas', 'invalidas', 'omitidas', 'duplicadas', 'iniciado_en', 'terminado_en', 'error_global']);
        });

        DB::statement("ALTER TABLE importaciones MODIFY COLUMN estado ENUM('borrador','pendiente','validada','preparada','procesando','completada','fallida','cancelada') NOT NULL DEFAULT 'borrador'");
        DB::statement("UPDATE importaciones SET estado = 'borrador' WHERE estado = 'pendiente'");
        DB::statement("UPDATE importaciones SET estado = 'validada' WHERE estado = 'preparada'");
        DB::statement("ALTER TABLE importaciones MODIFY COLUMN estado ENUM('borrador','validada','procesando','completada','fallida','cancelada') NOT NULL DEFAULT 'borrador'");
    }
};
