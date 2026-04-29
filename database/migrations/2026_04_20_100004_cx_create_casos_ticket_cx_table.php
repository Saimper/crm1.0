<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Especialización CTI 1:1 del caso para operaciones de CX (ticket de soporte).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('casos_ticket_cx', function (Blueprint $table): void {
            $table->unsignedBigInteger('caso_id')->primary();

            $table->foreign('caso_id')
                ->references('id')->on('casos')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('proyecto_id')
                ->constrained('proyectos')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->string('codigo_ticket', 50);
            $table->string('asunto', 255);
            $table->text('descripcion')->nullable();

            $table->foreignId('categoria_ticket_id')
                ->nullable()
                ->constrained('categorias_ticket')
                ->nullOnDelete();

            $table->foreignId('prioridad_ticket_id')
                ->nullable()
                ->constrained('prioridades_ticket')
                ->nullOnDelete();

            $table->foreignId('nivel_sla_id')
                ->nullable()
                ->constrained('niveles_sla')
                ->nullOnDelete();

            $table->foreignId('nivel_escalamiento_id')
                ->nullable()
                ->constrained('niveles_escalamiento')
                ->nullOnDelete();

            $table->timestamp('fecha_reporte');
            $table->timestamp('fecha_limite_sla')->nullable();

            $table->timestamp('creada_en')->useCurrent();
            $table->timestamp('actualizada_en')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['proyecto_id', 'codigo_ticket'], 'casos_ticket_cx_proyecto_codigo_unique');
            $table->index(['proyecto_id', 'categoria_ticket_id']);
            $table->index(['proyecto_id', 'prioridad_ticket_id']);
            $table->index(['proyecto_id', 'fecha_limite_sla']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('casos_ticket_cx');
    }
};
