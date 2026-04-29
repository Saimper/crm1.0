<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('importacion_filas', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('importacion_id')
                ->constrained('importaciones')
                ->cascadeOnDelete();

            $table->foreignId('proyecto_id')
                ->constrained('proyectos')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->unsignedInteger('numero_fila');
            $table->enum('estado', ['pendiente', 'valida', 'invalida', 'importada', 'omitida'])->default('pendiente');
            $table->json('payload');
            $table->string('mensaje_error', 500)->nullable();

            $table->unsignedBigInteger('entidad_id')->nullable();

            $table->timestamp('creada_en')->useCurrent();
            $table->timestamp('actualizada_en')->useCurrent()->useCurrentOnUpdate();

            $table->index(['importacion_id', 'estado'], 'importacion_filas_importacion_estado_idx');
            $table->index(['proyecto_id', 'importacion_id', 'numero_fila'], 'importacion_filas_proyecto_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('importacion_filas');
    }
};
