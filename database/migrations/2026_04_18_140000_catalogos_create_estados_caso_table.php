<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo POR PROYECTO de estados operativos de caso (§8.2 CLAUDE.md v2).
 * Los estados varían por mandante/proyecto (p.ej. cobranza: Vigente/En mora/Judicial;
 * cx: Abierto/Escalado/Resuelto; venta: Nuevo/Contactado/Ganado/Perdido).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estados_caso', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('proyecto_id')
                ->constrained('proyectos')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->string('codigo', 50);
            $table->string('nombre', 150);
            $table->string('descripcion', 500)->nullable();
            $table->boolean('activo')->default(true);
            $table->boolean('es_terminal')->default(false);
            $table->unsignedInteger('orden')->default(0);
            $table->json('metadata')->nullable();

            $table->timestamp('creada_en')->useCurrent();
            $table->timestamp('actualizada_en')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['proyecto_id', 'codigo'], 'estados_caso_proyecto_codigo_unique');
            $table->index(['proyecto_id', 'activo', 'orden']);
            $table->index(['proyecto_id', 'es_terminal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estados_caso');
    }
};
