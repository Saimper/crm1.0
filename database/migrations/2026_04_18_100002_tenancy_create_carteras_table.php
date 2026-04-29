<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carteras', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->foreignId('proyecto_id')
                ->constrained('proyectos')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->string('codigo', 80);
            $table->string('nombre', 200);
            $table->string('descripcion', 1000)->nullable();
            $table->boolean('activo')->default(true);

            $table->timestamp('creada_en')->useCurrent();
            $table->timestamp('actualizada_en')->useCurrent()->useCurrentOnUpdate();
            $table->timestamp('eliminada_en')->nullable();

            $table->unique(['proyecto_id', 'codigo'], 'carteras_proyecto_codigo_unique');
            $table->index(['proyecto_id', 'activo']);
            $table->index('eliminada_en');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carteras');
    }
};
