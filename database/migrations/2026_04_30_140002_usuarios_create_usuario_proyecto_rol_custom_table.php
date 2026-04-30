<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asignación de rol custom a usuario en proyecto (Fase 33).
 *
 * Tabla simétrica a `usuario_proyecto_rol`, sin tocar la base por seguridad.
 * `User::tienePermiso` consulta ambas y une el conjunto de permisos.
 *
 * F22 cartera-scoping no aplica en F33: se introducirá en F34+ con tabla
 * análoga `usuario_proyecto_rol_custom_cartera` si el negocio lo pide.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuario_proyecto_rol_custom', function (Blueprint $table): void {
            $table->foreignId('usuario_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('proyecto_id')
                ->constrained('proyectos')
                ->cascadeOnDelete();
            $table->foreignId('rol_custom_id')
                ->constrained('roles_custom')
                ->cascadeOnDelete();
            $table->boolean('activo')->default(true);
            $table->timestamp('creada_en')->useCurrent();
            $table->timestamp('actualizada_en')->useCurrent()->useCurrentOnUpdate();

            $table->primary(['usuario_id', 'proyecto_id', 'rol_custom_id'], 'uprc_primary');
            $table->index(['proyecto_id', 'activo'], 'uprc_proyecto_activo_idx');
            $table->index(['proyecto_id', 'rol_custom_id'], 'uprc_proyecto_rol_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario_proyecto_rol_custom');
    }
};
