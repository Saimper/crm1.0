<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Especialización CTI 1:1 del compromiso para CX: resolución de ticket.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compromisos_resolucion_ticket', function (Blueprint $table): void {
            $table->unsignedBigInteger('compromiso_id')->primary();

            $table->foreign('compromiso_id')
                ->references('id')->on('compromisos')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('proyecto_id')
                ->constrained('proyectos')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->foreignId('nivel_escalamiento_id')
                ->nullable()
                ->constrained('niveles_escalamiento')
                ->nullOnDelete();

            $table->string('accion_comprometida', 500);
            $table->timestamp('fecha_limite_sla');

            $table->timestamp('creada_en')->useCurrent();
            $table->timestamp('actualizada_en')->useCurrent()->useCurrentOnUpdate();

            $table->index(['proyecto_id', 'nivel_escalamiento_id'], 'resolucion_ticket_proyecto_nivel_idx');
            $table->index(['proyecto_id', 'fecha_limite_sla'], 'resolucion_ticket_proyecto_sla_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compromisos_resolucion_ticket');
    }
};
