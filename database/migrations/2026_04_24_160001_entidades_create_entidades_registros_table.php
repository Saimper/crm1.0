<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registros de las entidades configurables.
 *
 * Cada fila es un "registro" de una entidad definida (ej. una póliza específica).
 * Los valores de los campos del registro se guardan en `valores_campo_personalizado`
 * con `entidad_id = entidades_registros.id` y el ámbito `entidad_configurable` del
 * campo apuntando a `entidades_configurables.id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entidades_registros', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->foreignId('proyecto_id')
                ->constrained('proyectos')
                ->restrictOnDelete();

            $table->foreignId('entidad_configurable_id')
                ->constrained('entidades_configurables')
                ->cascadeOnDelete();

            $table->foreignId('caso_id')
                ->nullable()
                ->constrained('casos')
                ->nullOnDelete();

            $table->foreignId('persona_id')
                ->nullable()
                ->constrained('personas')
                ->nullOnDelete();

            $table->string('titulo', 255)->nullable(); // etiqueta visible del registro

            $table->foreignId('creado_por_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Convención: "eliminado_en" (masculino) concuerda con "registro";
            // el resto de tablas usa "eliminada_en" (femenino) por concordancia
            // con casos/personas/gestiones. Decisión F34C: aceptar la
            // divergencia gramatical antes que introducir un rename masivo.
            $table->timestamp('creado_en')->useCurrent();
            $table->timestamp('actualizado_en')->useCurrent()->useCurrentOnUpdate();
            $table->timestamp('eliminado_en')->nullable();

            $table->index(['proyecto_id', 'entidad_configurable_id', 'eliminado_en'], 'entidades_reg_lectura');
            $table->index(['caso_id'], 'entidades_reg_caso_idx');
            $table->index(['persona_id'], 'entidades_reg_persona_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entidades_registros');
    }
};
