<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('importaciones', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->foreignId('proyecto_id')
                ->constrained('proyectos')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->enum('tipo_entidad', ['persona']);
            $table->enum('estado', ['borrador', 'validada', 'procesando', 'completada', 'fallida', 'cancelada'])->default('borrador');

            $table->foreignId('usuario_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('nombre_archivo', 255);
            $table->unsignedInteger('total_filas')->default(0);
            $table->unsignedInteger('filas_ok')->default(0);
            $table->unsignedInteger('filas_error')->default(0);
            $table->unsignedInteger('filas_importadas')->default(0);

            $table->timestamp('creada_en')->useCurrent();
            $table->timestamp('actualizada_en')->useCurrent()->useCurrentOnUpdate();

            $table->index(['proyecto_id', 'estado', 'creada_en'], 'importaciones_proyecto_estado_idx');
            $table->index(['proyecto_id', 'usuario_id'], 'importaciones_proyecto_usuario_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('importaciones');
    }
};
