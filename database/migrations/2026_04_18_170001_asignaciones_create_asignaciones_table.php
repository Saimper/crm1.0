<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asignaciones', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->foreignId('proyecto_id')
                ->constrained('proyectos')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->foreignId('campana_id')
                ->constrained('campanas')
                ->restrictOnDelete();

            $table->foreignId('caso_id')
                ->constrained('casos')
                ->restrictOnDelete();

            $table->foreignId('usuario_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->date('fecha_asignacion');
            $table->unsignedInteger('prioridad')->default(100);
            $table->enum('estado', ['pendiente', 'en_trabajo', 'cerrada'])->default('pendiente');
            $table->timestamp('cerrada_en')->nullable();

            $table->timestamp('creada_en')->useCurrent();
            $table->timestamp('actualizada_en')->useCurrent()->useCurrentOnUpdate();

            // Índice obligatorio §4.5 CLAUDE.md v2.
            $table->index(['proyecto_id', 'usuario_id', 'estado']);
            $table->index(['proyecto_id', 'campana_id', 'estado']);
            $table->index(['proyecto_id', 'caso_id', 'estado']);
            $table->unique(['campana_id', 'caso_id'], 'asignaciones_campana_caso_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asignaciones');
    }
};
