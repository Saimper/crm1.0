<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Especialización CTI 1:1 del compromiso para Venta: cierre estimado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compromisos_cierre_venta', function (Blueprint $table): void {
            $table->unsignedBigInteger('compromiso_id')->primary();

            $table->foreign('compromiso_id')
                ->references('id')->on('compromisos')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('proyecto_id')
                ->constrained('proyectos')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->decimal('monto_cierre', 15, 2);
            $table->char('moneda', 3)->default('USD');

            $table->foreignId('etapa_embudo_id')
                ->nullable()
                ->constrained('etapas_embudo')
                ->nullOnDelete();

            $table->timestamp('creada_en')->useCurrent();
            $table->timestamp('actualizada_en')->useCurrent()->useCurrentOnUpdate();

            $table->index(['proyecto_id', 'etapa_embudo_id'], 'cierre_venta_proyecto_etapa_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compromisos_cierre_venta');
    }
};
