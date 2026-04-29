<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla pivote equipo_usuario. Asocia usuarios a equipos dentro de un proyecto.
 * proyecto_id se repite por redundancia controlada para mantener scope por proyecto
 * y facilitar queries scoped sin triple-join.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipo_usuario', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('equipo_id')
                ->constrained('equipos')
                ->cascadeOnDelete();

            $table->foreignId('usuario_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('proyecto_id')
                ->constrained('proyectos')
                ->restrictOnDelete();

            $table->boolean('activo')->default(true);
            $table->timestamp('creada_en')->useCurrent();

            $table->unique(['equipo_id', 'usuario_id'], 'equipo_usuario_unq');
            $table->index(['proyecto_id', 'usuario_id', 'activo'], 'equipo_usuario_proy_user_act_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipo_usuario');
    }
};
