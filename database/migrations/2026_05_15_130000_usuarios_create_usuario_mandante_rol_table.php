<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F38: rol scoped por mandante (no por proyecto). Usado para ADMIN_MANDANTE,
 * que administra N proyectos del mismo mandante sin necesitar pivot por
 * proyecto. Tabla simétrica a usuario_proyecto_rol pero scoped al mandante.
 *
 * No tiene cartera-scoping (F22) ni equipo: el rol es cross-proyecto del
 * mandante por definición.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuario_mandante_rol', function (Blueprint $table): void {
            $table->foreignId('usuario_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('mandante_id')
                ->constrained('mandantes')
                ->cascadeOnDelete();
            $table->foreignId('rol_id')
                ->constrained('roles')
                ->restrictOnDelete();
            $table->boolean('activo')->default(true);
            $table->timestamp('creada_en')->useCurrent();
            $table->timestamp('actualizada_en')->useCurrent()->useCurrentOnUpdate();

            $table->primary(['usuario_id', 'mandante_id', 'rol_id'], 'usuario_mandante_rol_primary');
            $table->index(['mandante_id', 'activo']);
            $table->index(['mandante_id', 'rol_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario_mandante_rol');
    }
};
