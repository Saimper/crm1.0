<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F35-B: agrega columna mapeo JSON a importaciones para almacenar el mapeo
 * {campo_sistema_codigo => columna_csv} aplicado por el wizard al construir
 * el payload canónico de cada fila. Se usa para auditoría y reusabilidad.
 *
 * El payload de importacion_filas sigue siendo canónico (mismas keys que esperan
 * los UseCases procesadores), por lo que su estructura no cambia y los workers
 * existentes (EjecutarImportacionJob) operan sin modificación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('importaciones', function (Blueprint $table): void {
            $table->json('mapeo')->nullable()->after('tipo_entidad');
        });
    }

    public function down(): void
    {
        Schema::table('importaciones', function (Blueprint $table): void {
            $table->dropColumn('mapeo');
        });
    }
};
