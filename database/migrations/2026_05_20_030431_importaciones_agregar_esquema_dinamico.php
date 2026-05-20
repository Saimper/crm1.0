<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F35-E: importaciones dinámicas — agrega columna esquema JSON y extiende
 * el enum modo para soportar insert, update, upsert.
 *
 * - esquema: JSON nullable que almacena el EsquemaImportacion serializado.
 *   Contiene target, proyecto_id, cartera_id, modo y la lista de columnas
 *   con su acción (mapear_sistema, crear_cp, ignorar), tipo inferido,
 *   y cuál es el identificador de persona.
 * - modo: se amplía de ['merge','skip_duplicados','overwrite'] a
 *   ['merge','skip_duplicados','overwrite','insert','update','upsert'].
 * - importacion_campos_personalizados: tabla de auditoría que rastrea
 *   qué campos personalizados se crearon/reutilizaron por cada importación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('importaciones', function (Blueprint $table): void {
            $table->json('esquema')->nullable()->after('mapeo');
            $table->unsignedInteger('insertadas')->default(0)->after('procesadas');
            $table->unsignedInteger('actualizadas')->default(0)->after('insertadas');
        });

        DB::statement(
            "ALTER TABLE importaciones MODIFY COLUMN modo "
            ."ENUM('merge','skip_duplicados','overwrite','insert','update','upsert') "
            ."NOT NULL DEFAULT 'upsert'"
        );

        Schema::create('importacion_campos_personalizados', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('importacion_id')->constrained('importaciones')->onDelete('cascade');
            $table->foreignId('campo_personalizado_id')->constrained('campos_personalizados')->onDelete('cascade');
            $table->string('columna_original');
            $table->timestamp('creada_en')->useCurrent();

            $table->unique(['importacion_id', 'campo_personalizado_id'], 'imp_cp_import_campo_unique');
            $table->index('importacion_id', 'imp_cp_importacion_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('importacion_campos_personalizados');

        DB::statement(
            "ALTER TABLE importaciones MODIFY COLUMN modo "
            ."ENUM('merge','skip_duplicados','overwrite') "
            ."NOT NULL DEFAULT 'merge'"
        );

        Schema::table('importaciones', function (Blueprint $table): void {
            $table->dropColumn(['esquema', 'insertadas', 'actualizadas']);
        });
    }
};
