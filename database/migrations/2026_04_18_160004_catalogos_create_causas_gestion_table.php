<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo genérico de causas usadas al registrar gestiones.
 * El significado depende del tipo de operación del proyecto:
 *   cobranza  → causas de mora
 *   cx        → causas de queja
 *   venta     → razones de rechazo
 *   servicio  → motivos de intervención
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('causas_gestion', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('proyecto_id')
                ->constrained('proyectos')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->string('codigo', 50);
            $table->string('nombre', 150);
            $table->boolean('activo')->default(true);
            $table->unsignedInteger('orden')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamp('creada_en')->useCurrent();
            $table->timestamp('actualizada_en')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['proyecto_id', 'codigo'], 'causas_gestion_proyecto_codigo_unique');
            $table->index(['proyecto_id', 'activo', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('causas_gestion');
    }
};
