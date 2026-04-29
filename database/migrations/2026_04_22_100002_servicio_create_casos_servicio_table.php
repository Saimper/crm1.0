<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Especialización CTI 1:1 del caso para operaciones de servicio técnico.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('casos_servicio', function (Blueprint $table): void {
            $table->unsignedBigInteger('caso_id')->primary();

            $table->foreign('caso_id')
                ->references('id')->on('casos')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('proyecto_id')
                ->constrained('proyectos')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->string('codigo_servicio', 50);

            $table->foreignId('tipo_accion_servicio_id')
                ->nullable()
                ->constrained('tipos_accion_servicio')
                ->nullOnDelete();

            $table->foreignId('estado_tecnico_id')
                ->nullable()
                ->constrained('estados_tecnicos')
                ->nullOnDelete();

            $table->string('direccion_servicio', 500)->nullable();
            $table->string('tecnico_asignado', 150)->nullable();

            $table->date('fecha_solicitud');
            $table->timestamp('fecha_programada')->nullable();

            $table->timestamp('creada_en')->useCurrent();
            $table->timestamp('actualizada_en')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['proyecto_id', 'codigo_servicio'], 'casos_servicio_proyecto_codigo_unique');
            $table->index(['proyecto_id', 'tipo_accion_servicio_id'], 'casos_servicio_proyecto_accion_idx');
            $table->index(['proyecto_id', 'estado_tecnico_id'], 'casos_servicio_proyecto_estado_idx');
            $table->index(['proyecto_id', 'fecha_programada'], 'casos_servicio_proyecto_fecha_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('casos_servicio');
    }
};
