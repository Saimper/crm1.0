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

            $table->foreignId('campana_id')
                ->constrained('campanas')
                ->restrictOnDelete();
            $table->foreignId('producto_id')
                ->constrained('productos')
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

            // Índice obligatorio §3.5
            $table->index(['usuario_id', 'estado']);
            $table->index(['campana_id', 'estado']);
            $table->index(['producto_id', 'estado']);
            // Un producto activo solo puede tener una asignación no cerrada por campaña
            $table->unique(['campana_id', 'producto_id'], 'asignaciones_campana_producto_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asignaciones');
    }
};
