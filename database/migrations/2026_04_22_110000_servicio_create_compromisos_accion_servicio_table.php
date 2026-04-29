<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Especialización CTI 1:1 del compromiso para Servicio: acción programada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compromisos_accion_servicio', function (Blueprint $table): void {
            $table->unsignedBigInteger('compromiso_id')->primary();

            $table->foreign('compromiso_id')
                ->references('id')->on('compromisos')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('proyecto_id')
                ->constrained('proyectos')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->foreignId('tipo_accion_servicio_id')
                ->nullable()
                ->constrained('tipos_accion_servicio')
                ->nullOnDelete();

            $table->string('descripcion_accion', 500);
            $table->timestamp('fecha_programada');
            $table->string('tecnico_asignado', 150)->nullable();

            $table->timestamp('creada_en')->useCurrent();
            $table->timestamp('actualizada_en')->useCurrent()->useCurrentOnUpdate();

            $table->index(['proyecto_id', 'tipo_accion_servicio_id'], 'accion_servicio_proyecto_tipo_idx');
            $table->index(['proyecto_id', 'fecha_programada'], 'accion_servicio_proyecto_fecha_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compromisos_accion_servicio');
    }
};
