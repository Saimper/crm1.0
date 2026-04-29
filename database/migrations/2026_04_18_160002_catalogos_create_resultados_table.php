<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de resultados de gestión (scoped por proyecto).
 * Las banderas que disparan invariantes de dominio son columnas explícitas
 * (no JSON metadata) para ser tipadas e indexables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resultados', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('proyecto_id')
                ->constrained('proyectos')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->string('codigo', 50);
            $table->string('nombre', 150);
            $table->string('descripcion', 500)->nullable();
            $table->boolean('activo')->default(true);
            $table->unsignedInteger('orden')->default(0);

            // Banderas de dominio (§5 CLAUDE.md v2).
            $table->boolean('es_contacto_efectivo')->default(false);
            $table->boolean('requiere_compromiso')->default(false);
            $table->boolean('requiere_causa')->default(false);

            $table->json('metadata')->nullable();
            $table->timestamp('creada_en')->useCurrent();
            $table->timestamp('actualizada_en')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['proyecto_id', 'codigo'], 'resultados_proyecto_codigo_unique');
            $table->index(['proyecto_id', 'activo', 'orden']);
            $table->index(['proyecto_id', 'es_contacto_efectivo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resultados');
    }
};
