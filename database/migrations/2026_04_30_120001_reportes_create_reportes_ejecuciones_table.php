<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reportes_ejecuciones', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->foreignId('definicion_id')
                ->constrained('reportes_definiciones')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->foreignId('proyecto_id')
                ->constrained('proyectos')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->foreignId('usuario_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->enum('formato', ['vista', 'csv', 'xlsx']);
            $table->unsignedInteger('total_filas')->default(0);
            $table->unsignedInteger('duracion_ms')->default(0);

            $table->timestamp('ejecutado_en')->useCurrent();

            $table->index(['proyecto_id', 'ejecutado_en'], 'reportes_ej_proyecto_fecha_idx');
            $table->index(['definicion_id', 'ejecutado_en'], 'reportes_ej_def_fecha_idx');
            $table->index(['proyecto_id', 'usuario_id', 'ejecutado_en'], 'reportes_ej_proyecto_usr_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reportes_ejecuciones');
    }
};
