<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entidades configurables (Fase 24).
 *
 * Una entidad configurable es una "tabla lógica" que un administrador define
 * para un proyecto (y opcionalmente restringida a una cartera). Ejemplos:
 *   - "Pólizas" en un proyecto de seguros
 *   - "Vehículos" en un proyecto de cobranza vehicular
 *   - "Bienes embargables" en un proyecto de cobranza judicial
 *
 * Apertura controlada (§20 CLAUDE.md): estas entidades REUTILIZAN el sistema de
 * campos personalizados (§7) como definición de columnas. Nuevo ámbito:
 * `entidad_configurable`. Cero fórmulas, triggers, rules engine. Solo datos tipados.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entidades_configurables', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->foreignId('proyecto_id')
                ->constrained('proyectos')
                ->restrictOnDelete();

            $table->foreignId('cartera_id')
                ->nullable()
                ->constrained('carteras')
                ->nullOnDelete();

            $table->string('codigo', 80);
            $table->string('nombre', 150);
            $table->string('descripcion', 500)->nullable();
            $table->string('icono', 50)->nullable();

            // Relación opcional a entidades del núcleo. Solo 1:N hacia el núcleo.
            // No se permite relación entre dos entidades configurables — ver §20.
            $table->enum('relacion_con', ['ninguna', 'caso', 'persona'])->default('ninguna');

            $table->boolean('activo')->default(true);
            $table->timestamp('creada_en')->useCurrent();
            $table->timestamp('actualizada_en')->useCurrent()->useCurrentOnUpdate();
            $table->timestamp('eliminada_en')->nullable();

            $table->unique(['proyecto_id', 'codigo'], 'entidades_conf_unq');
            $table->index(['proyecto_id', 'cartera_id', 'activo'], 'entidades_conf_lectura');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entidades_configurables');
    }
};
