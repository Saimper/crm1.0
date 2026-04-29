<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipos', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->foreignId('proyecto_id')
                ->constrained('proyectos')
                ->restrictOnDelete();

            $table->string('codigo', 50);
            $table->string('nombre', 150);
            $table->string('descripcion', 500)->nullable();
            $table->boolean('activo')->default(true);

            $table->timestamp('creada_en')->useCurrent();
            $table->timestamp('actualizada_en')->useCurrent()->useCurrentOnUpdate();
            $table->timestamp('eliminada_en')->nullable();

            $table->unique(['proyecto_id', 'codigo'], 'equipos_proyecto_codigo_unique');
            $table->index(['proyecto_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipos');
    }
};
