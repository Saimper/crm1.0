<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roles custom por proyecto (Fase 33).
 *
 * ADMIN_GLOBAL define un rol custom dentro de un proyecto combinando permisos
 * existentes (matriz F22). Roles base (SUPERVISOR/GESTOR/AUDITOR/ADMIN_GLOBAL)
 * NO viven aquí: siguen en `roles`. Estos son adicionales y reemplazables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles_custom', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->foreignId('proyecto_id')
                ->constrained('proyectos')
                ->cascadeOnDelete();

            $table->string('codigo', 60);
            $table->string('nombre', 150);
            $table->string('descripcion', 500)->nullable();
            $table->boolean('activo')->default(true);

            $table->foreignId('creado_por_usuario_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamp('creada_en')->useCurrent();
            $table->timestamp('actualizada_en')->useCurrent()->useCurrentOnUpdate();
            $table->timestamp('eliminada_en')->nullable();

            $table->unique(['proyecto_id', 'codigo'], 'roles_custom_proyecto_codigo_unique');
            $table->index(['proyecto_id', 'activo', 'eliminada_en'], 'roles_custom_proyecto_activo_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles_custom');
    }
};
