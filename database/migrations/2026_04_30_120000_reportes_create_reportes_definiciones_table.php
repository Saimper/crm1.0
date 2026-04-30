<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reportes_definiciones', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->foreignId('proyecto_id')
                ->constrained('proyectos')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->string('codigo', 80);
            $table->string('nombre', 200);
            $table->text('descripcion')->nullable();

            $table->enum('entidad_raiz', ['casos', 'gestiones', 'compromisos', 'personas']);

            $table->json('columnas');
            $table->json('filtros');
            $table->json('agrupaciones');
            $table->json('orden');

            $table->boolean('activo')->default(true);

            $table->foreignId('creado_por_usuario_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamp('creada_en')->useCurrent();
            $table->timestamp('actualizada_en')->useCurrent()->useCurrentOnUpdate();
            $table->timestamp('eliminada_en')->nullable();

            $table->unique(['proyecto_id', 'codigo'], 'reportes_def_proyecto_codigo_unique');
            $table->index(['proyecto_id', 'activo', 'eliminada_en'], 'reportes_def_proyecto_activo_idx');
            $table->index(['proyecto_id', 'entidad_raiz'], 'reportes_def_proyecto_entidad_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reportes_definiciones');
    }
};
