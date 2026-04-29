<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contactos', function (Blueprint $table): void {
            $table->id();

            // proyecto_id explícito para scope — la relación via persona existe pero
            // mantener la columna facilita índices compuestos y el trait PerteneceAProyecto.
            $table->foreignId('proyecto_id')
                ->constrained('proyectos')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->foreignId('persona_id')
                ->constrained('personas')
                ->cascadeOnDelete()
                ->restrictOnUpdate();

            $table->enum('tipo', ['telefono', 'correo', 'direccion']);
            $table->string('valor', 250);
            $table->string('etiqueta', 100)->nullable();
            $table->boolean('es_principal')->default(false);
            $table->boolean('activo')->default(true);

            $table->timestamp('creada_en')->useCurrent();
            $table->timestamp('actualizada_en')->useCurrent()->useCurrentOnUpdate();
            $table->timestamp('eliminada_en')->nullable();

            $table->index(['proyecto_id', 'persona_id', 'tipo', 'activo']);
            $table->index(['proyecto_id', 'persona_id', 'es_principal']);
            $table->index(['proyecto_id', 'eliminada_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contactos');
    }
};
