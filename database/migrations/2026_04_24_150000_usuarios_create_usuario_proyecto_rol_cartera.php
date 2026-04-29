<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivote secundaria opcional para scoping por cartera.
 *
 * Semántica:
 *   - Si para una combinación (usuario, proyecto, rol) NO existe ninguna fila en esta tabla,
 *     el rol aplica a TODO el proyecto (comportamiento histórico F1–F21, 100% retrocompatible).
 *   - Si existe al menos una fila, el rol aplica SOLO a las carteras listadas.
 *
 * No reemplaza `usuario_proyecto_rol`: ahí sigue viviendo la relación fundamental usuario↔rol↔proyecto.
 * Esta tabla solo restringe dicha relación a carteras específicas cuando se necesita granularidad.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuario_proyecto_rol_cartera', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('usuario_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('proyecto_id')
                ->constrained('proyectos')
                ->cascadeOnDelete();
            $table->foreignId('rol_id')
                ->constrained('roles')
                ->restrictOnDelete();
            $table->foreignId('cartera_id')
                ->constrained('carteras')
                ->cascadeOnDelete();

            $table->timestamp('creada_en')->useCurrent();

            $table->unique(
                ['usuario_id', 'proyecto_id', 'rol_id', 'cartera_id'],
                'upr_cartera_unique',
            );
            $table->index(['proyecto_id', 'cartera_id'], 'upr_cartera_proyecto_idx');
            $table->index(['usuario_id', 'proyecto_id'], 'upr_cartera_usuario_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario_proyecto_rol_cartera');
    }
};
