<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personas', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->foreignId('proyecto_id')
                ->constrained('proyectos')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->enum('tipo_persona', ['fisica', 'juridica']);
            $table->foreignId('tipo_identificacion_id')
                ->constrained('tipos_identificacion')
                ->restrictOnDelete()
                ->restrictOnUpdate();
            $table->string('identificacion', 50);

            $table->string('nombres', 150)->nullable();
            $table->string('apellidos', 150)->nullable();
            $table->string('razon_social', 250)->nullable();
            $table->date('fecha_nacimiento')->nullable();

            // Hash técnico opcional para dedupe futura (§2.1 CLAUDE.md v2).
            // Nunca se usa para autorizar visibilidad cross-proyecto.
            $table->string('hash_identidad', 64)->nullable();

            $table->timestamp('creada_en')->useCurrent();
            $table->timestamp('actualizada_en')->useCurrent()->useCurrentOnUpdate();
            $table->timestamp('eliminada_en')->nullable();

            $table->unique(
                ['proyecto_id', 'tipo_identificacion_id', 'identificacion'],
                'personas_proyecto_tipo_identif_unique'
            );
            $table->index(['proyecto_id', 'eliminada_en']);
            $table->index('hash_identidad');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personas');
    }
};
