<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificaciones', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->foreignId('proyecto_id')
                ->constrained('proyectos')
                ->restrictOnDelete();

            $table->foreignId('destinatario_usuario_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->enum('tipo', [
                'compromiso_por_vencer',
                'compromiso_vencido',
                'sla_en_riesgo',
                'compromiso_resuelto',
            ]);

            $table->string('entidad_tipo', 80);
            $table->unsignedBigInteger('entidad_id');

            $table->string('titulo', 255);
            $table->string('mensaje', 500)->nullable();
            $table->json('metadata')->nullable();

            $table->timestamp('leida_en')->nullable();
            $table->timestamp('creada_en')->useCurrent();

            $table->index(['proyecto_id', 'destinatario_usuario_id', 'leida_en'], 'notif_destinatario_leida_idx');
            $table->unique(
                ['proyecto_id', 'destinatario_usuario_id', 'tipo', 'entidad_tipo', 'entidad_id'],
                'notif_destino_entidad_tipo_unq'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificaciones');
    }
};
