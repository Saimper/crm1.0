<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campanas', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('codigo', 80)->unique();
            $table->string('nombre', 200);
            $table->string('descripcion', 1000)->nullable();
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->enum('estado', ['programada', 'activa', 'pausada', 'finalizada'])->default('programada');
            $table->foreignId('creada_por_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('creada_en')->useCurrent();
            $table->timestamp('actualizada_en')->useCurrent()->useCurrentOnUpdate();
            $table->timestamp('eliminada_en')->nullable();

            $table->index(['estado', 'fecha_inicio']);
            $table->index('eliminada_en');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campanas');
    }
};
