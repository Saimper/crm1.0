<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campanas', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->foreignId('proyecto_id')
                ->constrained('proyectos')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->string('codigo', 80);
            $table->string('nombre', 200);
            $table->string('descripcion', 1000)->nullable();

            $table->enum('estado', ['programada', 'activa', 'pausada', 'finalizada'])->default('programada');

            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();

            $table->foreignId('creada_por_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('creada_en')->useCurrent();
            $table->timestamp('actualizada_en')->useCurrent()->useCurrentOnUpdate();
            $table->timestamp('eliminada_en')->nullable();

            $table->unique(['proyecto_id', 'codigo'], 'campanas_proyecto_codigo_unique');
            $table->index(['proyecto_id', 'estado', 'fecha_inicio']);
            $table->index(['proyecto_id', 'eliminada_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campanas');
    }
};
