<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuario_proyecto_rol', function (Blueprint $table): void {
            $table->foreignId('usuario_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('proyecto_id')
                ->constrained('proyectos')
                ->cascadeOnDelete();
            $table->foreignId('rol_id')
                ->constrained('roles')
                ->restrictOnDelete();
            $table->foreignId('equipo_id')
                ->nullable()
                ->constrained('equipos')
                ->nullOnDelete();
            $table->boolean('activo')->default(true);
            $table->timestamp('creada_en')->useCurrent();
            $table->timestamp('actualizada_en')->useCurrent()->useCurrentOnUpdate();

            $table->primary(['usuario_id', 'proyecto_id', 'rol_id'], 'usuario_proyecto_rol_primary');
            $table->index(['proyecto_id', 'activo']);
            $table->index(['proyecto_id', 'rol_id']);
            $table->index('equipo_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario_proyecto_rol');
    }
};
