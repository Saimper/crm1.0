<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivote rol_custom ↔ permiso (Fase 33).
 *
 * Análogo a `rol_permiso` pero para roles custom. Solo permisos existentes
 * en la matriz F22 son asignables; *.definir queda excluido en Domain.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rol_custom_permiso', function (Blueprint $table): void {
            $table->foreignId('rol_custom_id')
                ->constrained('roles_custom')
                ->cascadeOnDelete();
            $table->foreignId('permiso_id')
                ->constrained('permisos')
                ->cascadeOnDelete();

            $table->primary(['rol_custom_id', 'permiso_id'], 'rol_custom_permiso_primary');
            $table->index('permiso_id', 'rol_custom_permiso_permiso_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rol_custom_permiso');
    }
};
