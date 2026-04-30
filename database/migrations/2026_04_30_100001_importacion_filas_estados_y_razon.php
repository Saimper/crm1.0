<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F31: estados de fila normalizados + razón de omisión.
 * - valida/importada → procesada (mismo concepto: fila procesada exitosamente).
 * - Agrega duplicada (registro existente bajo skip_duplicados).
 * - Agrega razon_omision (string explicativo).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE importacion_filas MODIFY COLUMN estado ENUM('pendiente','valida','procesada','duplicada','invalida','importada','omitida') NOT NULL DEFAULT 'pendiente'");
        DB::statement("UPDATE importacion_filas SET estado = 'procesada' WHERE estado IN ('valida','importada')");
        DB::statement("ALTER TABLE importacion_filas MODIFY COLUMN estado ENUM('pendiente','procesada','duplicada','invalida','omitida') NOT NULL DEFAULT 'pendiente'");

        Schema::table('importacion_filas', function (Blueprint $table): void {
            $table->string('razon_omision', 200)->nullable()->after('mensaje_error');
        });
    }

    public function down(): void
    {
        Schema::table('importacion_filas', function (Blueprint $table): void {
            $table->dropColumn('razon_omision');
        });

        DB::statement("ALTER TABLE importacion_filas MODIFY COLUMN estado ENUM('pendiente','valida','procesada','duplicada','invalida','importada','omitida') NOT NULL DEFAULT 'pendiente'");
        DB::statement("UPDATE importacion_filas SET estado = 'importada' WHERE estado = 'procesada'");
        DB::statement("UPDATE importacion_filas SET estado = 'omitida' WHERE estado = 'duplicada'");
        DB::statement("ALTER TABLE importacion_filas MODIFY COLUMN estado ENUM('pendiente','valida','invalida','importada','omitida') NOT NULL DEFAULT 'pendiente'");
    }
};
