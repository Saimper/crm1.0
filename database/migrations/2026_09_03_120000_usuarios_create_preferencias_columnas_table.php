<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F41: preferencias de columnas visibles por usuario, proyecto y vista.
 *
 * Guarda qué columnas ve cada usuario en un listado y en qué orden. Es preferencia
 * de presentación, no dato operativo: si la fila no existe, la vista cae en su
 * configuración por defecto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preferencias_columnas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('proyecto_id')->constrained('proyectos')->cascadeOnDelete();
            $table->string('vista', 50);
            $table->json('columnas');
            $table->timestamp('creada_en')->useCurrent();
            $table->timestamp('actualizada_en')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['usuario_id', 'proyecto_id', 'vista'], 'preferencias_columnas_unico');
            $table->index(['proyecto_id', 'vista'], 'preferencias_columnas_proyecto_vista');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preferencias_columnas');
    }
};
