<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Especialización CTI 1:1 del caso para operaciones de venta (lead/oportunidad).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('casos_lead_venta', function (Blueprint $table): void {
            $table->unsignedBigInteger('caso_id')->primary();

            $table->foreign('caso_id')
                ->references('id')->on('casos')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('proyecto_id')
                ->constrained('proyectos')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->string('codigo_lead', 50);

            $table->foreignId('producto_venta_id')
                ->nullable()
                ->constrained('productos_venta')
                ->nullOnDelete();

            $table->foreignId('etapa_embudo_id')
                ->nullable()
                ->constrained('etapas_embudo')
                ->nullOnDelete();

            $table->decimal('valor_estimado', 15, 2)->default(0);
            $table->char('moneda', 3)->default('USD');

            $table->string('origen_lead', 100)->nullable();
            $table->date('fecha_primer_contacto');
            $table->date('fecha_estimada_cierre')->nullable();

            $table->timestamp('creada_en')->useCurrent();
            $table->timestamp('actualizada_en')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['proyecto_id', 'codigo_lead'], 'casos_lead_venta_proyecto_codigo_unique');
            $table->index(['proyecto_id', 'etapa_embudo_id'], 'casos_lead_venta_proyecto_etapa_idx');
            $table->index(['proyecto_id', 'producto_venta_id'], 'casos_lead_venta_proyecto_producto_idx');
            $table->index(['proyecto_id', 'fecha_estimada_cierre'], 'casos_lead_venta_proyecto_cierre_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('casos_lead_venta');
    }
};
