<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditoria', function (Blueprint $table): void {
            $table->id();
            $table->string('entidad', 80);
            $table->unsignedBigInteger('entidad_id');
            $table->string('accion', 50);
            $table->foreignId('usuario_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->json('diff')->nullable();
            $table->json('contexto')->nullable();
            $table->timestamp('creada_en')->useCurrent();

            $table->index(['entidad', 'entidad_id', 'creada_en']);
            $table->index(['usuario_id', 'creada_en']);
            $table->index(['accion', 'creada_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditoria');
    }
};
