<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditorias', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->foreignId('proyecto_id')
                ->nullable()
                ->constrained('proyectos')
                ->nullOnDelete();

            $table->foreignId('usuario_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('entidad_tipo', 80);
            $table->unsignedBigInteger('entidad_id');

            $table->enum('evento', ['creado', 'actualizado', 'eliminado']);

            $table->json('datos_antes')->nullable();
            $table->json('datos_despues')->nullable();
            $table->json('cambios')->nullable();

            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();

            $table->timestamp('creada_en')->useCurrent();

            $table->index(['proyecto_id', 'entidad_tipo', 'entidad_id'], 'auditorias_proyecto_entidad_idx');
            $table->index(['proyecto_id', 'creada_en'], 'auditorias_proyecto_fecha_idx');
            $table->index(['usuario_id', 'creada_en'], 'auditorias_usuario_fecha_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditorias');
    }
};
