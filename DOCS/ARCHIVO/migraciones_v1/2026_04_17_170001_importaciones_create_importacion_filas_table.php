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
            $table->unsignedInteger('numero_fila');
            $table->json('datos_originales');
            $table->json('datos_normalizados')->nullable();
            $table->enum('estado', ['pendiente', 'valida', 'invalida', 'procesada', 'error'])
                ->default('pendiente');
            $table->json('errores')->nullable();
            $table->unsignedBigInteger('entidad_creada_id')->nullable();

            $table->timestamp('creada_en')->useCurrent();
            $table->timestamp('actualizada_en')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['importacion_id', 'numero_fila']);
            $table->index(['importacion_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('importacion_filas');
    }
};
